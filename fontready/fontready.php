<?php
/**
 * Plugin Name: FontReady
 * Plugin URI: https://fontready.com
 * Description: Publish converted fonts into Elementor Pro Custom Fonts. No FontReady account or manual connection keys required.
 * Version: 0.4.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Ademola Alabi
 * Author URI: https://ademolaalabi.com
 * License: GPL-2.0-or-later
 * Text Domain: fontready
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/elementor.php';

final class FontReady_Plugin {
    const REGISTRY = 'fontready_fonts';
    const CONNECTION = 'fontready_connection';
    const ORIGIN = 'https://fontready.com';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'connect' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ) );
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
        foreach ( self::active_faces() as $face ) { $fonts[ $face['family'] ] = isset( $face['elementor_post_id'] ) ? 'custom' : 'fontready'; }
        return $fonts;
    }
    public static function styles() {
        $faces = self::active_faces();
        if ( ! $faces || wp_style_is( 'fontready', 'enqueued' ) ) { return; }
        $css = '';
        $variants = array();
        foreach ( $faces as $face ) { $variants[ $face['family'] . '|' . $face['weight'] . '|' . $face['style'] ][ $face['format'] ] = $face; }
        foreach ( $variants as $assets ) {
            $sources = array();
            foreach ( array( 'woff2'=>'woff2', 'woff'=>'woff', 'ttf'=>'truetype', 'otf'=>'opentype', 'svg'=>'svg', 'eot'=>'embedded-opentype' ) as $key=>$format ) {
                if ( ! isset( $assets[$key] ) ) { continue; }
                $face = $assets[$key];
                $url = $face['url'] . ( $key === 'svg' ? '#' . ( $face['svg_id'] ?? '' ) : '' );
                $sources[] = 'url(' . wp_json_encode( esc_url_raw( $url ) ) . ') format("' . $format . '")';
            }
            $css .= '@font-face{font-family:' . wp_json_encode( $face['family'], JSON_UNESCAPED_UNICODE ) . ';src:' . implode( ',', $sources ) . ';font-weight:' . $face['weight'] . ';font-style:' . $face['style'] . ';font-display:swap;}';
        }
        wp_register_style( 'fontready', false, array(), '0.4.1' );
        wp_enqueue_style( 'fontready' );
        wp_add_inline_style( 'fontready', $css );
    }

    public static function routes() {
        register_rest_route( 'fontready/v1', '/connection', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => array( __CLASS__, 'authorize' ) ) );
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
            header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
        }
        return $served;
    }
    public static function authorize( $request ) {
        $connection = get_option( self::CONNECTION, array() );
        $authorization = $request->get_header( 'authorization' );
        $origin = get_http_origin();
        if ( ( $origin && $origin !== self::ORIGIN ) || ! preg_match( '/\ABearer ([a-f0-9]{64})\z/', $authorization ?? '', $match ) || empty( $connection['hash'] ) || ( $connection['expires'] ?? 0 ) <= time() || ! hash_equals( $connection['hash'], hash( 'sha256', $match[1] ) ) || ! user_can( (int) ( $connection['user'] ?? 0 ), 'manage_options' ) || ! user_can( (int) ( $connection['user'] ?? 0 ), 'upload_files' ) ) {
            return new WP_Error( 'fontready_auth', 'Reconnect your website through FontReady.', array( 'status' => 401 ) );
        }
        if ( ! self::pro() ) { return new WP_Error( 'fontready_pro', 'Activate Elementor Pro to publish fonts.', array( 'status' => 409 ) ); }
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
        $obsolete_attachments = array();
        $native = null;
        try {
            $native = new FontReady_Elementor();
            $faces = get_option( self::REGISTRY, array() );
            $original = $faces;
            $faces = FontReady_Elementor::retained_faces( $faces );
            $upload = wp_upload_dir();
            if ( $upload['error'] ) { throw new RuntimeException( 'WordPress uploads are unavailable.' ); }
            $directory = $upload['basedir'] . '/fontready';
            if ( ! wp_mkdir_p( $directory ) ) { throw new RuntimeException( 'The font directory could not be created.' ); }
            foreach ( $fonts as $font ) {
                $id = $font['id'];
                // Migrate the earlier single-file variant without leaving duplicate assets.
                $legacy = $font['face_key'];
                if ( isset( $faces[$legacy] ) && $faces[$legacy]['format'] === $font['format'] ) { $faces[$id] = $faces[$legacy]; $faces[$id]['id'] = $id; unset( $faces[$legacy] ); }
                $weights = array( '100'=>'thin', '200'=>'extra-light', '300'=>'light', '400'=>'regular', '500'=>'medium', '600'=>'semi-bold', '700'=>'bold', '800'=>'extra-bold', '900'=>'black' );
                $label = $weights[$font['weight']] ?? str_replace( ' ', '-', $font['weight'] );
                $prefix = ( sanitize_title( $font['family'] ) ?: 'font' ) . '-' . $label . ( $font['style'] !== 'normal' ? '-' . $font['style'] : '' ) . '-fontready-';
                if ( isset( $faces[ $id ] ) && $faces[ $id ]['hash'] === $font['hash'] && $faces[ $id ]['format'] === $font['format'] && is_file( $faces[ $id ]['path'] ) && strpos( basename( $faces[ $id ]['path'] ), $prefix ) === 0 ) { continue; }
                $filename = $prefix . substr( wp_generate_uuid4(), 0, 8 ) . '.' . $font['format'];
                $path = $directory . '/' . $filename;
                $created[] = $path;
                if ( file_put_contents( $path, $font['data'], LOCK_EX ) !== strlen( $font['data'] ) ) { throw new RuntimeException( 'The font file could not be saved.' ); }
                chmod( $path, 0644 );
                if ( isset( $faces[ $id ] ) ) { $obsolete[] = $faces[ $id ]['path']; if ( ! empty( $faces[ $id ]['attachment_id'] ) ) { $obsolete_attachments[] = $faces[ $id ]['attachment_id']; } }
                unset( $font['data'] );
                $faces[ $id ] = array_merge( $font, array( 'path' => $path, 'url' => set_url_scheme( $upload['baseurl'], 'https' ) . '/fontready/' . $filename ) );
            }
            $native->sync( $faces, array_values( array_unique( array_column( $fonts, 'family' ) ) ) );
            if ( $faces !== $original && ! update_option( self::REGISTRY, $faces, false ) ) { throw new RuntimeException( 'The font registry could not be saved.' ); }
            $native->invalidate();
            foreach ( $obsolete_attachments as $id ) { wp_delete_attachment( $id, true ); }
            foreach ( $obsolete as $path ) { wp_delete_file( $path ); }
            return new WP_REST_Response( array( 'imported' => count( $fonts ), 'variants' => self::variant_count( $fonts ), 'existing_variants' => max( 0, self::variant_count( array_filter( $faces, function( $face ) use ( $fonts ) { return in_array( $face['family'], array_column( $fonts, 'family' ), true ); } ) ) - self::variant_count( $fonts ) ), 'families' => array_values( array_unique( array_column( $fonts, 'family' ) ) ), 'formats' => array_values( array_unique( array_column( $fonts, 'format' ) ) ), 'manage_url' => admin_url( 'edit.php?post_type=elementor_font' ), 'message' => 'Fonts published to Elementor Custom Fonts. Refresh the editor to select them.' ), 200 );
        } catch ( Throwable $error ) {
            if ( $native ) { $native->rollback(); }
            foreach ( $created as $path ) { wp_delete_file( $path ); }
            return new WP_Error( 'fontready_save', $error->getMessage(), array( 'status' => 500 ) );
        } finally { delete_option( 'fontready_import_lock' ); }
    }

    public static function active_faces() {
        return FontReady_Elementor::retained_faces( get_option( self::REGISTRY, array() ) );
    }
    public static function variant_count( $faces ) { return count( array_unique( array_map( function( $face ) { return $face['family'] . '|' . $face['weight'] . '|' . $face['style']; }, $faces ) ) ); }
    public static function pro() { return defined( 'ELEMENTOR_PRO_VERSION' ); }
    public static function status() {
        $faces = self::active_faces();
        $ready = false;
        try { new FontReady_Elementor(); $ready = true; } catch ( Throwable $error ) {}
        return new WP_REST_Response( array( 'site' => home_url( '/' ), 'elementor_pro' => self::pro(), 'families' => count( array_unique( array_column( $faces, 'family' ) ) ), 'variants' => self::variant_count( $faces ), 'native_custom_fonts' => $ready, 'multi_format_import' => true ), 200 );
    }
    public static function admin_styles( $hook ) {
        if ( $hook === 'toplevel_page_fontready' ) { wp_enqueue_style( 'fontready-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), '0.4.1' ); }
    }
    public static function connect() {
        if ( ! isset( $_POST['fontready_action'] ) || ! current_user_can( 'manage_options' ) ) { return; }
        check_admin_referer( 'fontready_manage' );
        $action = sanitize_key( wp_unslash( $_POST['fontready_action'] ) );
        if ( $action === 'disconnect' ) {
            delete_option( self::CONNECTION );
            wp_safe_redirect( admin_url( 'admin.php?page=fontready' ) ); exit;
        }
        if ( $action !== 'connect' ) { return; }
        $state = isset( $_POST['fontready_state'] ) ? sanitize_text_field( wp_unslash( $_POST['fontready_state'] ) ) : '';
        if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $state ) || empty( $_POST['fontready_confirm'] ) || ! self::pro() || ! current_user_can( 'upload_files' ) || wp_parse_url( rest_url(), PHP_URL_SCHEME ) !== 'https' ) {
            wp_die( esc_html__( 'Confirm your website, activate Elementor Pro and use HTTPS before connecting.', 'fontready' ) );
        }
        $secret = bin2hex( random_bytes( 32 ) );
        $expires = time() + 7 * DAY_IN_SECONDS;
        if ( ! update_option( self::CONNECTION, array( 'hash' => hash( 'sha256', $secret ), 'expires' => $expires, 'user' => get_current_user_id(), 'connected' => time() ), false ) ) {
            wp_die( esc_html__( 'The connection could not be saved. Please try again.', 'fontready' ) );
        }
        // Fixed service destination; credentials live in a fragment, never a query or server log.
        $payload = array( 'state' => $state, 'endpoint' => rest_url( 'fontready/v1/fonts' ), 'status' => rest_url( 'fontready/v1/connection' ), 'key' => $secret, 'expires' => $expires, 'site' => home_url( '/' ) );
        $job = isset( $_POST['fontready_job'] ) ? sanitize_text_field( wp_unslash( $_POST['fontready_job'] ) ) : '';
        $return = self::ORIGIN . '/font-to-elementor-pro/' . ( preg_match( '/\A[a-f0-9-]{36}\z/', $job ) ? '?kit=' . rawurlencode( $job ) : '' );
        wp_redirect( $return . '#fontready=' . rawurlencode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) ) ); exit;
    }
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $faces = self::active_faces();
        $connection = get_option( self::CONNECTION, array() );
        $connected = ! empty( $connection['expires'] ) && $connection['expires'] > time();
        $state = isset( $_GET['fontready_connect'] ) ? sanitize_text_field( wp_unslash( $_GET['fontready_connect'] ) ) : '';
        $pending = preg_match( '/\A[a-f0-9]{64}\z/', $state );
        ?>
        <div class="wrap fontready-admin">
            <header class="fr-header"><span class="fr-mark" aria-hidden="true"><svg viewBox="0 0 40 40" fill="none"><path d="M11 29V11h16v5H16v4h8v5h-8v4Z" fill="currentColor"/><path d="m24 29 3 3 7-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span><h1><?php echo esc_html__( 'Font', 'fontready' ); ?><span><?php echo esc_html__( 'Ready', 'fontready' ); ?></span></h1><span class="fr-pill"><?php echo esc_html__( 'FOR ELEMENTOR PRO', 'fontready' ); ?></span></header>
            <div class="fr-intro"><p class="fr-eyebrow"><?php echo esc_html__( 'YOUR FONTS. YOUR WEBSITE.', 'fontready' ); ?></p><h2><?php echo esc_html__( 'Ready for Elementor.', 'fontready' ); ?></h2><p><?php echo esc_html__( 'Convert with FontReady. Install here. Keep creating.', 'fontready' ); ?></p></div>
            <div class="fr-grid"><section class="fr-card"><div class="fr-card-heading"><h3><?php echo esc_html__( 'Website connection', 'fontready' ); ?></h3><span class="fr-status <?php echo $connected ? 'is-connected' : ''; ?>"><?php echo $connected ? esc_html__( 'Connected', 'fontready' ) : esc_html__( 'Ready to connect', 'fontready' ); ?></span></div>
                <p class="fr-domain"><?php echo esc_html( home_url( '/' ) ); ?></p>
                <p><?php echo esc_html__( 'Your activated plugin allows FontReady to install fonts on this website. Connect through your WordPress administrator account.', 'fontready' ); ?></p>
                <?php if ( ! self::pro() ) { ?><p class="fr-warning"><?php echo esc_html__( 'Activate Elementor Pro to connect this website.', 'fontready' ); ?></p><?php } ?>
                <?php if ( $pending ) { ?>
                    <form method="post"><?php wp_nonce_field( 'fontready_manage' ); ?><input type="hidden" name="fontready_state" value="<?php echo esc_attr( $state ); ?>">
                    <input type="hidden" name="fontready_confirm" value="1"><input type="hidden" name="fontready_job" value="<?php echo esc_attr( isset( $_GET['fontready_job'] ) ? sanitize_text_field( wp_unslash( $_GET['fontready_job'] ) ) : '' ); ?>">
                    <p><?php echo esc_html__( 'Elementor Pro is installed. Approving allows FontReady to publish the selected fonts to this website and shares its domain and installation totals.', 'fontready' ); ?></p>
                    <button class="fr-button" name="fontready_action" value="connect" <?php disabled( ! self::pro() ); ?>><?php echo esc_html__( 'Approve font publishing ↗', 'fontready' ); ?></button></form>
                <?php } else { ?><a class="fr-button" href="https://fontready.com/font-to-elementor-pro/"><?php echo esc_html__( 'Connect on FontReady ↗', 'fontready' ); ?></a><?php } ?>
                <?php if ( $connected ) { ?><form method="post" class="fr-disconnect"><?php wp_nonce_field( 'fontready_manage' ); ?><button name="fontready_action" value="disconnect"><?php echo esc_html__( 'Disconnect website', 'fontready' ); ?></button></form><?php } ?>
                <p class="fr-footnote"><?php echo esc_html__( 'Only install fonts you have permission to use. Fonts stay on your website after the FontReady download expires.', 'fontready' ); ?></p>
            </section><section class="fr-card fr-stat"><span class="fr-stat-icon" aria-hidden="true"><?php echo esc_html__( 'Aa', 'fontready' ); ?></span><p class="fr-number"><?php echo esc_html( count( array_unique( array_column( $faces, 'family' ) ) ) ); ?></p><h3><?php echo esc_html__( 'Fonts installed', 'fontready' ); ?></h3><p><?php echo esc_html__( 'with FontReady ·', 'fontready' ); ?><?php echo esc_html( self::variant_count( $faces ) ); ?><?php echo esc_html__( 'variants', 'fontready' ); ?></p><div class="fr-stat-tip"><?php echo esc_html__( 'Your fonts appear in Elementor → Custom Fonts. Refresh the editor after publishing. Manage fonts in Elementor.', 'fontready' ); ?></div></section></div>
            <footer class="fr-footer"><?php echo esc_html__( 'FontReady 0.4.1 ·', 'fontready' ); ?><a href="https://fontready.com/privacy/"><?php echo esc_html__( 'Privacy & service details', 'fontready' ); ?></a><span><?php echo esc_html__( 'Made for better typography.', 'fontready' ); ?></span></footer>
        </div>
        <?php
    }

}
FontReady_Plugin::init();
register_deactivation_hook( __FILE__, function() { delete_option( FontReady_Plugin::CONNECTION ); delete_option( 'fontready_import_lock' ); } );
