<?php
/**
 * Plugin Name: WP Plugin Deploy
 * Description: Registers WordPress Abilities for safe plugin deployment, rollback, and status inspection.
 * Version: 0.1.0
 * Author: Wachiravit Thitagarn
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: wp-plugin-deploy
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'WP_PLUGIN_DEPLOY_VERSION', '0.1.0' );
define( 'WP_PLUGIN_DEPLOY_FILE', __FILE__ );
define( 'WP_PLUGIN_DEPLOY_DIR', plugin_dir_path( __FILE__ ) );
foreach ( array('class-filesystem.php','class-package-resolver.php','class-package-validator.php','class-deployment-store.php','class-backup-manager.php','class-plugin-inspector.php','class-rollback-manager.php','class-deployer.php','class-abilities.php') as $file ) { require_once WP_PLUGIN_DEPLOY_DIR . 'includes/' . $file; }
WP_Plugin_Deploy_Abilities::init();
