<?php
/** Validate the bounded JSON import before touching files or options. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function fontready_validate_fonts( $payload ) {
    if ( ! is_array( $payload ) || ( $payload['version'] ?? null ) !== 1 || ! isset( $payload['fonts'] ) || ! is_array( $payload['fonts'] ) || count( $payload['fonts'] ) < 1 || count( $payload['fonts'] ) > 50 ) {
        return new WP_Error( 'fontready_invalid', 'Choose between 1 and 10 fonts.', array( 'status' => 400 ) );
    }
    $validated = array();
    $total = 0;
    $seen = array();
    foreach ( $payload['fonts'] as $font ) {
        $svg = array();
        if ( ! is_array( $font ) ) { return new WP_Error( 'fontready_invalid', 'Invalid font.', array( 'status' => 400 ) ); }
        $family = $font['family'] ?? '';
        $weight = $font['weight'] ?? '';
        $style = $font['style'] ?? '';
        $format = $font['format'] ?? '';
        $encoded = $font['data'] ?? '';
        if ( ! is_string( $family ) || ! preg_match( '/\A[\p{L}\p{N}_. -]{1,100}\z/u', $family ) || trim( $family ) === '' || ! is_string( $weight ) || ! preg_match( '/\A([1-9][0-9]{0,2}|1000)(?: ([1-9][0-9]{0,2}|1000))?\z/', $weight, $weights ) || ( isset( $weights[2] ) && (int) $weights[2] < (int) $weights[1] ) || ! in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) || ! in_array( $format, array( 'woff2', 'woff', 'ttf', 'otf', 'svg', 'eot' ), true ) || ! is_string( $encoded ) || strlen( $encoded ) > 6990508 ) {
            return new WP_Error( 'fontready_invalid', 'Invalid font metadata or size.', array( 'status' => 400 ) );
        }
        $data = base64_decode( $encoded, true );
        $size = $data === false ? 0 : strlen( $data );
        $total += $size;
        if ( $size < 12 || $size > 5 * 1024 * 1024 || $total > 25 * 1024 * 1024 ) {
            return new WP_Error( 'fontready_size', 'Maximum 5 MB per font and 25 MB per batch.', array( 'status' => 400 ) );
        }
        if ( ! fontready_valid_asset( $data, $format ) ) { return new WP_Error( 'fontready_type', 'Invalid or unsafe font file.', array( 'status' => 400 ) ); }
        $face_key = hash( 'sha256', $family . '|' . $weight . '|' . $style );
        $id = hash( 'sha256', $family . '|' . $weight . '|' . $style . '|' . $format );
        if ( isset( $seen[ $id ] ) ) { return new WP_Error( 'fontready_duplicate', 'Two files use the same family, weight and style. Convert them separately.', array( 'status' => 400 ) ); }
        $seen[ $id ] = true;
        if ( $format === 'svg' ) { preg_match( '/<font\s[^>]*\bid="([^"]+)"/', $data, $svg ); }
        $validated[] = array( 'svg_id' => $svg[1] ?? '', 'id' => $id, 'face_key' => $face_key, 'family' => $family, 'weight' => $weight, 'style' => $style, 'format' => $format, 'data' => $data, 'hash' => hash( 'sha256', $data ) );
    }
    if ( count( array_unique( array_column( $validated, 'face_key' ) ) ) > 10 ) { return new WP_Error( 'fontready_size', 'Choose up to 10 font variants.', array( 'status' => 400 ) ); }
    return $validated;
}

/** Validate SVG font markup with a strict inert vocabulary; no scripts, links or entities. */
function fontready_valid_svg( $data ) {
    $xml = preg_replace( '/\A<\?xml[^?]*\?>\s*/', '', $data );
    if ( strpos( $xml, '<!' ) !== false || strpos( $xml, '<?' ) !== false || ! preg_match( '/<font\s/', $xml ) || ! preg_match( '/<font-face\s/', $xml ) || preg_match( '/&(?!amp;|lt;|gt;|quot;|apos;|#(?:[0-9]+|x[0-9a-fA-F]+);)/', $xml ) ) { return false; }
    if ( ! preg_match_all( '/<([^>]+)>/s', $xml, $tags ) ) { return false; }
    $allowed = array( 'svg', 'metadata', 'defs', 'font', 'font-face', 'missing-glyph', 'glyph' );
    $attrs = array( 'xmlns', 'version', 'id', 'horiz-adv-x', 'font-family', 'font-weight', 'font-style', 'units-per-em', 'ascent', 'descent', 'unicode', 'glyph-name', 'd' );
    foreach ( $tags[1] as $tag ) {
        if ( ! preg_match( '/\A\/?([a-z-]+)(.*)\z/s', $tag, $parts ) || ! in_array( $parts[1], $allowed, true ) ) { return false; }
        $tail = preg_replace_callback( '/\s+([a-z-]+)\s*=\s*("[^"<]*"|\'[^\'<]*\')/s', function( $match ) use ( $attrs ) {
            if ( ! in_array( $match[1], $attrs, true ) ) { return 'INVALID'; }
            if ( $match[1] === 'xmlns' && trim( $match[2], "\'\"") !== 'http://www.w3.org/2000/svg' ) { return 'INVALID'; }
            return '';
        }, $parts[2] );
        if ( trim( $tail, " /\t\r\n" ) !== '' ) { return false; }
    }
    return true;
}
function fontready_valid_asset( $data, $format ) {
    $size = strlen( $data );
    if ( $format === 'svg' ) { return fontready_valid_svg( $data ); }
    if ( $format === 'eot' ) {
        if ( $size < 96 ) { return false; }
        $header = unpack( 'Vsize/Vfont_size/Vversion/Vflags', substr( $data, 0, 16 ) );
        if ( $header['size'] !== $size || $header['version'] !== 0x10000 || $header['flags'] !== 0 || substr( $data, 34, 2 ) !== "\x4c\x50" ) { return false; }
        $offset = 80;
        for ( $i=0; $i<4; $i++ ) {
            if ( $offset + 4 > $size || substr( $data, $offset, 2 ) !== "\0\0" ) { return false; }
            $length = unpack( 'v', substr( $data, $offset+2, 2 ) )[1];
            if ( $length % 2 ) { return false; }
            $offset += 4 + $length;
        }
        return $offset + $header['font_size'] === $size && fontready_valid_asset( substr( $data, $offset ), 'ttf' );
    }
    $signature = array( 'woff2'=>'wOF2', 'woff'=>'wOFF', 'ttf'=>"\x00\x01\x00\x00", 'otf'=>'OTTO' );
    if ( $size < 12 || ! isset( $signature[$format] ) || substr( $data, 0, 4 ) !== $signature[$format] ) { return false; }
    if ( $format === 'woff' || $format === 'woff2' ) {
        if ( $size < ( $format === 'woff2' ? 48 : 44 ) ) { return false; }
        $h = unpack( 'Nlength/ntables/nreserved/Nsfnt', substr( $data, 8, 12 ) );
        return $h['length'] === $size && $h['tables'] >= 1 && $h['tables'] <= 4096 && $h['reserved'] === 0 && $h['sfnt'] <= 50*1024*1024;
    }
    $count = unpack( 'n', substr( $data, 4, 2 ) )[1];
    if ( $count < 1 || 12 + $count*16 > $size ) { return false; }
    for ( $i=0; $i<$count; $i++ ) {
        $table = unpack( 'Noffset/Nlength', substr( $data, 20+$i*16, 8 ) );
        if ( $table['offset'] > $size || $table['length'] > $size-$table['offset'] ) { return false; }
    }
    return true;
}
