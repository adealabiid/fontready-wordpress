<?php
/** Native Elementor Pro Custom Fonts adapter. All persistence uses WordPress APIs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class FontReady_Elementor {
    private $manager;
    private $type;
    private $snapshots = array();
    private $created = array();
    private $attachments = array();
    public function __construct() {
        $module_class = 'ElementorPro\\Modules\\AssetsManager\\Module';
        $manager_class = 'ElementorPro\\Modules\\AssetsManager\\AssetTypes\\Fonts_Manager';
        if ( ! class_exists( $module_class ) || ! class_exists( $manager_class ) ) { throw new RuntimeException( 'Elementor Pro Custom Fonts is unavailable. Activate Elementor Pro and try again.' ); }
        $module = $module_class::instance();
        $this->manager = $module->get_component( 'fonts-manager' );
        $this->type = $this->manager ? $this->manager->get_font_type_object( 'custom' ) : null;
        if ( ! $this->type || ! is_callable( array( $this->type, 'save_meta' ) ) || ! defined( get_class( $this->type ) . '::FONT_META_KEY' ) || ! post_type_exists( $manager_class::CPT ) ) {
            throw new RuntimeException( 'This Elementor Pro version does not expose the supported Custom Fonts interface. No fonts were installed.' );
        }
    }
    public function sync( &$faces, $families ) {
        $manager_class = get_class( $this->manager );
        $cpt = $manager_class::CPT;
        foreach ( $families as $family ) {
            // Find only plugin-owned records; never overwrite an existing user-created family.
            $posts = get_posts( array( 'post_type' => $cpt, 'post_status' => array( 'publish', 'draft', 'private', 'trash' ), 'title' => $family, 'posts_per_page' => -1 ) );
            $post_id = 0;
            foreach ( $posts as $post ) {
                if ( get_post_meta( $post->ID, '_fontready_owned', true ) !== '1' ) { throw new RuntimeException( 'A font named ' . $family . ' already exists in Elementor. Rename the family or remove that existing record before publishing.' ); }
                if ( $post->post_status === 'trash' ) { throw new RuntimeException( 'Restore or permanently delete the trashed Elementor font ' . $family . ' before publishing.' ); }
                $post_id = $post->ID;
            }
            if ( ! $post_id ) {
                $post_id = wp_insert_post( array( 'post_type' => $cpt, 'post_title' => $family, 'post_status' => 'draft' ), true );
                if ( is_wp_error( $post_id ) || ! $post_id ) { throw new RuntimeException( 'The Elementor font record could not be created.' ); }
                $this->created[] = $post_id;
                update_post_meta( $post_id, '_fontready_owned', '1' );
            } else { if ( get_post_status( $post_id ) !== 'publish' ) { throw new RuntimeException( 'An existing draft font must be published or removed in Elementor before updating it.' ); } $this->snapshots[ $post_id ] = get_post_meta( $post_id ); }
            $rows = array();
            foreach ( $faces as &$face ) {
                if ( $face['family'] !== $family ) { continue; }
                if ( $face['format'] === 'otf' ) { throw new RuntimeException( 'Select WOFF2, WOFF or TTF when converting for Elementor Pro. Direct OTF imports are not supported by this adapter.' ); }
                if ( empty( $face['attachment_id'] ) || get_post_status( $face['attachment_id'] ) !== 'inherit' ) {
                    $mime = array( 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf' )[ $face['format'] ];
                    $attachment = wp_insert_attachment( array( 'post_title' => $family . ' ' . $face['weight'] . ' ' . $face['style'], 'post_mime_type' => $mime, 'post_status' => 'inherit', 'post_parent' => $post_id ), $face['path'], $post_id, true );
                    if ( is_wp_error( $attachment ) || ! $attachment ) { throw new RuntimeException( 'The font could not be registered in the WordPress media library.' ); }
                    $this->attachments[] = $attachment;
                    $face['attachment_id'] = $attachment;
                }
                $face['elementor_post_id'] = $post_id;
                $rows[] = array( 'font_weight' => $face['weight'], 'font_style' => $face['style'], $face['format'] => array( 'id' => (string) $face['attachment_id'], 'url' => $face['url'] ) );
            }
            unset( $face );
            // Let the installed Elementor implementation generate its native metadata and CSS.
            $this->type->save_meta( $post_id, array( 'font_face' => $rows ) );
            $meta_key = constant( get_class( $this->type ) . '::FONT_META_KEY' );
            if ( get_post_meta( $post_id, $meta_key, true ) !== $rows ) { throw new RuntimeException( 'Elementor did not save the font variants. No installation was completed.' ); }
            $terms = wp_set_object_terms( $post_id, 'custom', $manager_class::TAXONOMY );
            if ( is_wp_error( $terms ) ) { throw new RuntimeException( 'The Elementor font type could not be saved.' ); }
            $result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
            if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'The Elementor font could not be published.' ); }
        }
    }
    public function invalidate() {
        foreach ( array( $this->manager, $this->type ) as $object ) {
            foreach ( ( new ReflectionClass( $object ) )->getConstants() as $name => $value ) {
                if ( strpos( $name, 'OPTION' ) !== false && is_string( $value ) && strpos( $value, 'elementor' ) === 0 ) { delete_option( $value ); }
            }
        }
    }
    public function rollback() {
        foreach ( $this->snapshots as $post_id => $metadata ) {
            foreach ( get_post_meta( $post_id ) as $key => $values ) { delete_post_meta( $post_id, $key ); }
            foreach ( $metadata as $key => $values ) { foreach ( $values as $value ) { add_post_meta( $post_id, $key, maybe_unserialize( $value ) ); } }
        }
        // Attachments are removed without removing font files owned by the caller's rollback.
        foreach ( $this->attachments as $id ) { delete_post_meta( $id, '_wp_attached_file' ); wp_delete_attachment( $id, true ); }
        foreach ( $this->created as $id ) { wp_delete_post( $id, true ); }
        $this->invalidate();
    }
}
