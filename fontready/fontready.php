<?php
/**
 * Plugin Name: FontReady
 * Plugin URI: https://fontready.com
 * Description: Publish converted FontReady fonts to your own WordPress site and use them in Elementor. No FontReady account required.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Ademola Alabi
 * Author URI: https://ademolaalabi.com
 * License: GPL-2.0-or-later
 * Text Domain: fontready
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/includes/validation.php';

final class FontReady_Plugin {
    const REGISTRY = 'fontready_fonts';
    const CONNECTION = 'fontready_connection';
    const ORIGIN = 'https://fontready.com';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 20, 4 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
        add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'styles' ) );
        add_filter( 'elementor/fonts/groups', array( __CLASS__, 'groups' ) );
        add_filter( 'elementor/fonts/additional_fonts', array( __CLASS__, 'fonts' ) );
    }

    public static function menu() { add_menu_page( 'FontReady', 'FontReady', 'manage_options', 'fontready', array( __CLASS__, 'page' ), 'dashicons-editor-textcolor', 81 ); }
    public static function groups( $groups ) { $groups['fontready'] = 'FontReady'; return $groups; }
    public static function fonts( $fonts ) {
        foreach ( get_option( self::REGISTRY, array() ) as $face ) { $fonts[ $face['family'] ] = 'fontready'; }
        return $fonts;
    }
    public static function styles() {
        $faces = get_option( self::REGISTRY, array() );
        if ( ! $faces || wp_style_is( 'fontready', 'enqueued' ) ) { return; }
        $css = '';
        foreach ( $faces as $face ) {
            $format = array( 'ttf' => 'truetype', 'otf' => 'opentype', 'woff' => 'woff', 'woff2' => 'woff2' )[ $face['format'] ];
            $css .= '@font-face{font-family:' . wp_json_encode( $face['family'], JSON_UNESCAPED_UNICODE ) . ';src:url(' . wp_json_encode( esc_url_raw( $face['url'] ) ) . ') format("' . $format . '");font-weight:' . $face['weight'] . ';font-style:' . $face['style'] . ';font-display:swap;}';
        }
        wp_register_style( 'fontready', false, array(), '0.1.0' );
        wp_enqueue_style( 'fontready' );
        wp_add_inline_style( 'fontready', $css );
    }

    public static function routes() {
        register_rest_route( 'fontready/v1', '/fonts', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'import' ), 'permission_callback' => array( __CLASS__, 'authorize' ) ) );
    }
    public static function cors( $served, $result, $request, $server ) {
        if ( strpos( $request->get_route(), '/fontready/v1/' ) !== 0 ) { return $served; }
        header_remove( 'Access-Control-Allow-Origin' );
        header_remove( 'Access-Control-Allow-Credentials' );
        header( 'Vary: Origin', false );
        header( 'Cache-Control: private, no-store' );
        if ( get_http_origin() === self::ORIGIN ) {
            header( 'Access-Control-Allow-Origin: ' . self::ORIGIN );
            header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
        }
        return $served;
    }
    public static function authorize( $request ) {
        $connection = get_option( self::CONNECTION, array() );
        $authorization = $request->get_header( 'authorization' );
        $origin = get_http_origin();
        if ( ( $origin && $origin !== self::ORIGIN ) || ! preg_match( '/\ABearer ([a-f0-9]{64})\z/', $authorization ?? '', $match ) || empty( $connection['hash'] ) || ( $connection['expires'] ?? 0 ) <= time() || ! hash_equals( $connection['hash'], hash( 'sha256', $match[1] ) ) || ! user_can( (int) ( $connection['user'] ?? 0 ), 'manage_options' ) || ! user_can( (int) ( $connection['user'] ?? 0 ), 'upload_files' ) ) {
            return new WP_Error( 'fontready_auth', 'Connection expired or revoked. Generate a new key in WordPress → FontReady.', array( 'status' => 401 ) );
        }
        return true;
    }

    public static function import( $request ) {
        if ( strlen( $request->get_body() ) > 36 * 1024 * 1024 ) { return new WP_Error( 'fontready_size', 'Import payload is too large.', array( 'status' => 413 ) ); }
        $fonts = fontready_validate_fonts( $request->get_json_params() );
        if ( is_wp_error( $fonts ) ) { return $fonts; }
        // Serialize registry writes without letting a retry delete an active lock.
        if ( ! add_option( 'fontready_import_lock', time(), '', false ) ) { return new WP_Error( 'fontready_busy', 'Another font change is running. Try again shortly.', array( 'status' => 409 ) ); }
        $created = array();
        $obsolete = array();
        try {
            $faces = get_option( self::REGISTRY, array() );
            $original = $faces;
            $upload = wp_upload_dir();
            if ( $upload['error'] ) { throw new RuntimeException( 'WordPress uploads are unavailable.' ); }
            $directory = $upload['basedir'] . '/fontready';
            if ( ! wp_mkdir_p( $directory ) ) { throw new RuntimeException( 'The font directory could not be created.' ); }
            foreach ( $fonts as $font ) {
                $id = $font['id'];
                if ( isset( $faces[ $id ] ) && $faces[ $id ]['hash'] === $font['hash'] && $faces[ $id ]['format'] === $font['format'] && is_file( $faces[ $id ]['path'] ) ) { continue; }
                $filename = $id . '-' . wp_generate_uuid4() . '.' . $font['format'];
                $path = $directory . '/' . $filename;
                $created[] = $path;
                if ( file_put_contents( $path, $font['data'], LOCK_EX ) !== strlen( $font['data'] ) ) { throw new RuntimeException( 'The font file could not be saved.' ); }
                chmod( $path, 0644 );
                if ( isset( $faces[ $id ] ) ) { $obsolete[] = $faces[ $id ]['path']; }
                unset( $font['data'] );
                $faces[ $id ] = array_merge( $font, array( 'path' => $path, 'url' => $upload['baseurl'] . '/fontready/' . $filename ) );
            }
            if ( count( $faces ) > 100 ) { throw new RuntimeException( 'FontReady supports up to 100 stored font variants. Remove unused fonts first.' ); }
            if ( $faces !== $original && ! update_option( self::REGISTRY, $faces, false ) ) { throw new RuntimeException( 'The font registry could not be saved.' ); }
            foreach ( $obsolete as $path ) { wp_delete_file( $path ); }
            return new WP_REST_Response( array( 'imported' => count( $fonts ), 'families' => array_values( array_unique( array_column( $fonts, 'family' ) ) ), 'message' => 'Fonts published. Refresh the Elementor editor to select them under FontReady.' ), 200 );
        } catch ( Throwable $error ) {
            foreach ( $created as $path ) { wp_delete_file( $path ); }
            return new WP_Error( 'fontready_save', $error->getMessage(), array( 'status' => 500 ) );
        } finally { delete_option( 'fontready_import_lock' ); }
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $key = '';
        $notice = '';
        if ( isset( $_POST['fontready_action'] ) ) {
            check_admin_referer( 'fontready_manage' );
            $action = sanitize_key( wp_unslash( $_POST['fontready_action'] ) );
            if ( $action === 'connect' ) {
                if ( ! current_user_can( 'upload_files' ) || wp_parse_url( rest_url(), PHP_URL_SCHEME ) !== 'https' ) { $notice = 'Publishing requires HTTPS and permission to upload files.'; }
                else {
                    $key = bin2hex( random_bytes( 32 ) );
                    update_option( self::CONNECTION, array( 'hash' => hash( 'sha256', $key ), 'expires' => time() + HOUR_IN_SECONDS, 'user' => get_current_user_id() ), false );
                    $notice = 'New connection key generated. It expires in one hour. Previous keys are revoked.';
                }
            } elseif ( $action === 'revoke' ) { delete_option( self::CONNECTION ); $notice = 'Connection revoked. Published fonts remain on this site.'; }
            elseif ( $action === 'remove' && isset( $_POST['family'] ) ) {
                if ( ! add_option( 'fontready_import_lock', time(), '', false ) ) { $notice = 'Another font change is running. Try again shortly.'; }
                else {
                    try {
                        $family = sanitize_text_field( wp_unslash( $_POST['family'] ) );
                        $faces = get_option( self::REGISTRY, array() );
                        $removed = array_filter( $faces, function( $face ) use ( $family ) { return $face['family'] === $family; } );
                        $remaining = array_diff_key( $faces, $removed );
                        if ( ! $removed || update_option( self::REGISTRY, $remaining, false ) ) { foreach ( $removed as $face ) { wp_delete_file( $face['path'] ); } $notice = 'Font family removed.'; }
                        else { $notice = 'The font family could not be removed.'; }
                    } finally { delete_option( 'fontready_import_lock' ); }
                }
            }
        }
        $faces = get_option( self::REGISTRY, array() );
        $connection = get_option( self::CONNECTION, array() );
        ?>
        <div class="wrap" style="max-width:960px">
            <h1>FontReady <span style="font-size:14px;font-weight:400">0.1.0</span></h1>
            <p>Convert on FontReady. Publish here. Your fonts stay on your WordPress site.</p>
            <?php if ( $notice ) { ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php } ?>
            <div class="card" style="max-width:none;padding:24px">
                <h2>Connect without an account</h2>
                <ol><li>Generate a temporary key below.</li><li>Open <a href="https://fontready.com" target="_blank" rel="noopener noreferrer">FontReady</a> and convert your fonts. Choose WOFF2 for modern browsers.</li><li>Choose “Publish to WordPress”, paste the connection details, review the families and publish.</li></ol>
                <p><strong>Status:</strong> <?php echo ! empty( $connection['expires'] ) && $connection['expires'] > time() ? 'Key active until ' . esc_html( wp_date( 'H:i T', $connection['expires'] ) ) : 'Disconnected'; ?></p>
                <form method="post"><?php wp_nonce_field( 'fontready_manage' ); ?><button class="button button-primary" name="fontready_action" value="connect">Generate connection key</button> <button class="button" name="fontready_action" value="revoke">Revoke connection</button></form>
                <?php if ( $key ) { ?>
                    <p><label for="fontready-details"><strong>Copy these connection details to FontReady:</strong></label></p>
                    <textarea id="fontready-details" class="large-text code" rows="4" readonly autocomplete="off"><?php echo esc_textarea( wp_json_encode( array( 'endpoint' => rest_url( 'fontready/v1/fonts' ), 'key' => $key ), JSON_UNESCAPED_SLASHES ) ); ?></textarea>
                    <p>Shown once. Keep this key private. It allows font publishing for one hour; you can revoke it above.</p>
                <?php } ?>
            </div>
            <h2>Published fonts</h2>
            <p>In Elementor, refresh the editor and choose your family under Typography → Font Family → FontReady. For other themes, apply the family with CSS.</p>
            <table class="widefat striped"><thead><tr><th>Family</th><th>Weights and styles</th><th>Manage</th></tr></thead><tbody>
            <?php foreach ( array_unique( array_column( $faces, 'family' ) ) as $family ) { ?>
                <tr><td><?php echo esc_html( $family ); ?></td><td><?php echo esc_html( implode( ', ', array_map( function( $face ) { return $face['weight'] . ' ' . $face['style']; }, array_filter( $faces, function( $face ) use ( $family ) { return $face['family'] === $family; } ) ) ) ); ?></td><td><form method="post"><?php wp_nonce_field( 'fontready_manage' ); ?><input type="hidden" name="family" value="<?php echo esc_attr( $family ); ?>"><button class="button" name="fontready_action" value="remove">Remove family</button></form></td></tr>
            <?php } ?>
            <?php if ( ! $faces ) { ?><tr><td colspan="3">Your published fonts will appear here.</td></tr><?php } ?>
            </tbody></table><p>Deactivation keeps fonts saved but stops loading them. Reactivate to restore them. Only publish fonts you have permission to use on your website.</p>
        </div>
        <?php
    }
}
FontReady_Plugin::init();
register_deactivation_hook( __FILE__, function() { delete_option( FontReady_Plugin::CONNECTION ); delete_option( 'fontready_import_lock' ); } );
