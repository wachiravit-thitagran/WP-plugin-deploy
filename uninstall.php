<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'wp_plugin_deploy_history' );
delete_option( 'wp_plugin_deploy_backups' );
