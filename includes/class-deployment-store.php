<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }
class WP_Plugin_Deploy_Deployment_Store {
    const OPTION = 'wp_plugin_deploy_history';
    const MAX_PER_PLUGIN = 20;
    public function record( array $event ) {
        $slug = sanitize_key( $event['plugin_slug'] ?? '' );
        if ( '' === $slug ) { return; }
        $all = get_option( self::OPTION, array() );
        if ( ! is_array( $all ) ) { $all = array(); }
        $event['timestamp'] = $event['timestamp'] ?? gmdate( 'c' );
        unset( $event['token'], $event['password'], $event['authorization'] );
        $all[ $slug ] = array_slice( array_merge( array( $event ), $all[ $slug ] ?? array() ), 0, self::MAX_PER_PLUGIN );
        update_option( self::OPTION, $all, false );
    }
    public function latest( $slug ) {
        $history = $this->history( $slug );
        return $history[0] ?? null;
    }
    public function history( $slug ) {
        $all = get_option( self::OPTION, array() );
        $slug = sanitize_key( $slug );
        return is_array( $all ) && isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : array();
    }
}
