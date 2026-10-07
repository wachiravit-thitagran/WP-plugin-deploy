<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }

class WP_Plugin_Deploy_Package_Resolver {
    public function resolve( array $request ) {
        $source = isset( $request['source'] ) ? trim( (string) $request['source'] ) : '';
        if ( '' === $source || ! wp_http_validate_url( $source ) ) {
            return new WP_Error( 'invalid_source', 'Source must be a valid HTTPS URL.' );
        }

        $parts = wp_parse_url( $source );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) ) {
            return new WP_Error( 'invalid_source', 'Only HTTPS sources are supported.' );
        }

        if ( 'github.com' === strtolower( $parts['host'] ) ) {
            $path = trim( (string) ( $parts['path'] ?? '' ), '/' );
            $segments = explode( '/', $path );
            if ( count( $segments ) < 2 || empty( $segments[0] ) || empty( $segments[1] ) ) {
                return new WP_Error( 'invalid_source', 'Invalid GitHub repository URL.' );
            }
            $owner = preg_replace( '/[^A-Za-z0-9_.-]/', '', $segments[0] );
            $repo  = preg_replace( '/\.git$/', '', preg_replace( '/[^A-Za-z0-9_.-]/', '', $segments[1] ) );
            if ( '' === $owner || '' === $repo ) {
                return new WP_Error( 'invalid_source', 'Invalid GitHub repository URL.' );
            }
            $ref = isset( $request['ref'] ) ? trim( (string) $request['ref'] ) : '';
            if ( '' !== $ref && ! preg_match( '/^[A-Za-z0-9._\/-]+$/', $ref ) ) {
                return new WP_Error( 'invalid_source', 'Invalid Git reference.' );
            }
            $api = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/zipball';
            if ( '' !== $ref ) {
                $api .= '/' . str_replace( '%2F', '/', rawurlencode( $ref ) );
            }
            return array(
                'source_type'      => 'github',
                'download_url'     => $api,
                'source_reference' => 'https://github.com/' . $owner . '/' . $repo,
                'ref'              => $ref,
                'ref_type'         => sanitize_key( $request['ref_type'] ?? '' ),
                'plugin_slug_hint' => sanitize_key( $repo ),
            );
        }

        if ( ! preg_match( '/\.zip(?:$|\?)/i', $source ) ) {
            return new WP_Error( 'invalid_source', 'Direct sources must point to a ZIP package.' );
        }

        return array(
            'source_type'      => 'zip',
            'download_url'     => esc_url_raw( $source ),
            'source_reference' => esc_url_raw( $source ),
            'ref'              => '',
            'ref_type'         => '',
        );
    }
}
