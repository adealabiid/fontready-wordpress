<?php
/** Validate the bounded JSON import before touching files or options. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function fontready_validate_fonts( $payload ) {
    if ( ! is_array( $payload ) || ( $payload['version'] ?? null ) !== 1 || ! isset( $payload['fonts'] ) || ! is_array( $payload['fonts'] ) || count( $payload['fonts'] ) < 1 || count( $payload['fonts'] ) > 10 ) {
        return new WP_Error( 'fontready_invalid', 'Choose between 1 and 10 fonts.', array( 'status' => 400 ) );
    }
    $validated = array();
    $total = 0;
    $seen = array();
    foreach ( $payload['fonts'] as $font ) {
        if ( ! is_array( $font ) ) { return new WP_Error( 'fontready_invalid', 'Invalid font.', array( 'status' => 400 ) ); }
        $family = $font['family'] ?? '';
        $weight = $font['weight'] ?? '';
        $style = $font['style'] ?? '';
        $format = $font['format'] ?? '';
        $encoded = $font['data'] ?? '';
        if ( ! is_string( $family ) || ! preg_match( '/\A[\p{L}\p{N}_. -]{1,100}\z/u', $family ) || trim( $family ) === '' || ! is_string( $weight ) || ! preg_match( '/\A([1-9][0-9]{0,2}|1000)(?: ([1-9][0-9]{0,2}|1000))?\z/', $weight, $weights ) || ( isset( $weights[2] ) && (int) $weights[2] < (int) $weights[1] ) || ! in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) || ! in_array( $format, array( 'woff2', 'woff', 'ttf', 'otf' ), true ) || ! is_string( $encoded ) || strlen( $encoded ) > 6990508 ) {
            return new WP_Error( 'fontready_invalid', 'Invalid font metadata or size.', array( 'status' => 400 ) );
        }
        $data = base64_decode( $encoded, true );
        $size = $data === false ? 0 : strlen( $data );
        $total += $size;
        if ( $size < 12 || $size > 5 * 1024 * 1024 || $total > 25 * 1024 * 1024 ) {
            return new WP_Error( 'fontready_size', 'Maximum 5 MB per font and 25 MB per batch.', array( 'status' => 400 ) );
        }
        $signature = array( 'woff2' => 'wOF2', 'woff' => 'wOFF', 'ttf' => "\x00\x01\x00\x00", 'otf' => 'OTTO' );
        if ( substr( $data, 0, 4 ) !== $signature[ $format ] ) {
            return new WP_Error( 'fontready_type', 'The file does not match its font format.', array( 'status' => 400 ) );
        }
        if ( $format === 'woff' || $format === 'woff2' ) {
            $minimum = $format === 'woff2' ? 48 : 44;
            if ( $size < $minimum ) { return new WP_Error( 'fontready_type', 'Invalid font header.', array( 'status' => 400 ) ); }
            $header = unpack( 'Nlength/ntables/nreserved/Nsfnt', substr( $data, 8, 12 ) );
            if ( $header['length'] !== $size || $header['tables'] < 1 || $header['tables'] > 4096 || $header['reserved'] !== 0 || $header['sfnt'] > 50 * 1024 * 1024 ) {
                return new WP_Error( 'fontready_type', 'Invalid font header.', array( 'status' => 400 ) );
            }
        } else {
            $count = unpack( 'n', substr( $data, 4, 2 ) )[1];
            if ( $count < 1 || 12 + $count * 16 > $size ) { return new WP_Error( 'fontready_type', 'Invalid font tables.', array( 'status' => 400 ) ); }
            for ( $i = 0; $i < $count; $i++ ) {
                $table = unpack( 'Noffset/Nlength', substr( $data, 12 + $i * 16 + 8, 8 ) );
                if ( $table['offset'] > $size || $table['length'] > $size - $table['offset'] ) { return new WP_Error( 'fontready_type', 'Invalid font table bounds.', array( 'status' => 400 ) ); }
            }
        }
        $id = hash( 'sha256', $family . '|' . $weight . '|' . $style );
        if ( isset( $seen[ $id ] ) ) { return new WP_Error( 'fontready_duplicate', 'Two files use the same family, weight and style. Convert them separately.', array( 'status' => 400 ) ); }
        $seen[ $id ] = true;
        $validated[] = array( 'id' => $id, 'family' => $family, 'weight' => $weight, 'style' => $style, 'format' => $format, 'data' => $data, 'hash' => hash( 'sha256', $data ) );
    }
    return $validated;
}
