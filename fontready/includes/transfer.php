<?php
/** Small authenticated requests, private temporary staging, one atomic import. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class FontReady_Transfer {
    const STATE = 'fontready_transfer';
    const LIMIT = 36 * 1024 * 1024;
    const CHUNK = 128 * 1024;
    public static function cleanup( $state ) {
        if ( ! empty( $state['path'] ) && is_file( $state['path'] ) ) { wp_delete_file( $state['path'] ); }
    }
    public static function expire() {
        $state = get_option( self::STATE, array() );
        if ( $state && ( $state['expires'] ?? 0 ) <= time() && ! get_option( 'fontready_transfer_lock' ) ) { self::cleanup( $state ); delete_option( self::STATE ); }
    }
    public static function accept( $payload ) {
        if ( ! add_option( 'fontready_transfer_lock', time(), '', false ) ) { return new WP_Error( 'fontready_busy', 'Another upload request is running. Retry shortly.', array( 'status' => 409 ) ); }
        try { return self::receive( $payload ); }
        catch ( Throwable $error ) { return new WP_Error( 'fontready_upload', 'WordPress could not complete the font upload. Retry publishing; check the server error log if this continues.', array( 'status' => 500 ) ); }
        finally { delete_option( 'fontready_transfer_lock' ); }
    }
    private static function receive( $payload ) {
        $part = $payload['upload'] ?? null;
        if ( ! is_array( $part ) || ! is_string( $part['id'] ?? null ) || ! preg_match( '/\A[a-f0-9]{32}\z/', $part['id'] ?? '' ) ) { return new WP_Error( 'fontready_upload', 'Invalid upload identifier.', array( 'status' => 400 ) ); }
        $connection = get_option( FontReady_Plugin::CONNECTION, array() );
        $scope = $connection['hash'] ?? '';
        $state = get_option( self::STATE, array() );
        if ( $state && ( ( $state['expires'] ?? 0 ) < time() || ( $state['scope'] ?? '' ) !== $scope ) ) { self::cleanup( $state ); delete_option( self::STATE ); $state = array(); }
        if ( ! empty( $part['commit'] ) ) {
            if ( ! $state || $state['id'] !== $part['id'] ) { return new WP_Error( 'fontready_upload', 'This upload expired. Retry publishing the prepared font.', array( 'status' => 409 ) ); }
            if ( isset( $state['result'] ) ) { return new WP_REST_Response( $state['result'], 200 ); }
            if ( $state['next'] !== $state['total'] ) { return new WP_Error( 'fontready_upload', 'The font upload is incomplete. Retry publishing.', array( 'status' => 409 ) ); }
            $body = file_get_contents( $state['path'] );
            if ( $body === false || ! hash_equals( $state['sha256'], hash( 'sha256', $body ) ) ) { return new WP_Error( 'fontready_upload', 'Font upload checksum mismatch. Retry publishing.', array( 'status' => 400 ) ); }
            $request = new WP_REST_Request( 'POST' );
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( $body );
            $result = FontReady_Plugin::import( $request );
            if ( is_wp_error( $result ) ) { return $result; }
            $state['result'] = $result->get_data();
            if ( ! update_option( self::STATE, $state, false ) ) { return new WP_Error( 'fontready_upload', 'Fonts were installed but the upload receipt could not be saved. Check Elementor Custom Fonts.', array( 'status' => 500 ) ); }
            self::cleanup( $state );
            return $result;
        }
        $index = $part['index'] ?? null; $total = $part['total'] ?? null;
        $encoded = $part['data'] ?? null; $checksum = $part['sha256'] ?? '';
        if ( ! is_int( $index ) || ! is_int( $total ) || $index < 0 || $total < 1 || $total > 288 || $index >= $total || ! is_string( $encoded ) || strlen( $encoded ) > self::CHUNK * 4 / 3 || ! is_string( $checksum ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $checksum ) ) { return new WP_Error( 'fontready_upload', 'Invalid font upload part.', array( 'status' => 400 ) ); }
        $bytes = base64_decode( $encoded, true );
        if ( $bytes === false || strlen( $bytes ) < 1 || strlen( $bytes ) > self::CHUNK ) { return new WP_Error( 'fontready_upload', 'Invalid font upload data.', array( 'status' => 400 ) ); }
        if ( $state && $state['id'] !== $part['id'] && ! isset( $state['result'] ) && $index !== 0 ) { return new WP_Error( 'fontready_upload', 'Another upload is active. Retry publishing.', array( 'status' => 409 ) ); }
        if ( ! $state || $state['id'] !== $part['id'] ) {
            if ( $index !== 0 ) { return new WP_Error( 'fontready_upload', 'Start the font upload again.', array( 'status' => 409 ) ); }
            self::cleanup( $state );
            $temporary = realpath( sys_get_temp_dir() ); $root = realpath( ABSPATH );
            if ( ! $temporary || ! is_writable( $temporary ) || ( $root && ( $temporary === $root || strpos( $temporary, $root . DIRECTORY_SEPARATOR ) === 0 ) ) ) { return new WP_Error( 'fontready_upload', 'WordPress needs a writable private system temporary directory for font publishing.', array( 'status' => 500 ) ); }
            $path = tempnam( $temporary, 'fontready-' );
            if ( ! $path ) { return new WP_Error( 'fontready_upload', 'Could not prepare the font upload.', array( 'status' => 500 ) ); }
            chmod( $path, 0600 );
            wp_schedule_single_event( time() + 600, 'fontready_cleanup_transfer' );
            $state = array( 'id' => $part['id'], 'scope' => $scope, 'path' => $path, 'expires' => time() + 600, 'next' => 0, 'total' => $total, 'bytes' => 0, 'sha256' => $checksum );
        }
        if ( $state['total'] !== $total || ! hash_equals( $state['sha256'], $checksum ) || isset( $state['result'] ) ) { return new WP_Error( 'fontready_upload', 'The font upload changed. Start publishing again.', array( 'status' => 409 ) ); }
        $part_hash = hash( 'sha256', $bytes );
        if ( $index === $state['next'] - 1 && hash_equals( $state['last_hash'] ?? '', $part_hash ) ) { return new WP_REST_Response( array( 'received' => $state['next'], 'total' => $total ), 200 ); }
        if ( $index !== $state['next'] || $state['bytes'] + strlen( $bytes ) > self::LIMIT ) { return new WP_Error( 'fontready_upload', 'Font upload is out of order or too large.', array( 'status' => 400 ) ); }
        // Use the recorded offset: retry after a database failure overwrites the same part.
        $file = fopen( $state['path'], 'c+b' );
        if ( ! $file ) { return new WP_Error( 'fontready_upload', 'The temporary font upload is unavailable.', array( 'status' => 500 ) ); }
        $written = false;
        if ( fseek( $file, $state['bytes'] ) === 0 ) { $written = fwrite( $file, $bytes ); ftruncate( $file, $state['bytes'] + strlen( $bytes ) ); }
        fclose( $file );
        if ( $written !== strlen( $bytes ) ) { return new WP_Error( 'fontready_upload', 'Could not save a font upload part.', array( 'status' => 500 ) ); }
        $state['bytes'] += strlen( $bytes ); $state['next']++; $state['last_hash'] = $part_hash;
        if ( ! update_option( self::STATE, $state, false ) ) { self::cleanup( $state ); delete_option( self::STATE ); return new WP_Error( 'fontready_upload', 'Could not save the font upload progress. Retry publishing.', array( 'status' => 500 ) ); }
        return new WP_REST_Response( array( 'received' => $state['next'], 'total' => $total ), 200 );
    }
}
