<?php
/*
Plugin Name: Network Plugin Auditor
Plugin URI: http://wordpress.org/support/plugin/network-plugin-auditor
Description: Adds columns to your Network Admin on the Sites, Themes and Plugins pages to show which of your sites have each plugin and theme activated.  Now you can easily determine which plugins and themes are used on your network sites and which can be safely removed.
Version: 1.10.1
Author: Katherine Semel
Author URI: http://bonsaibudget.com/
Network: true
Text Domain: network-plugin-auditor
Domain Path: /languages
*/

namespace NetworkPluginAuditor;

use WP_CLI;
/**
 * Kick it off.
 */
add_action( 'plugins_loaded', function() {
	require_once __DIR__ . '/inc/class-network-plugin-auditor.php';
	$plugin = NetworkPluginAuditor::get_instance();
	$plugin->init();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/inc/class-audit-plugins-command.php';
		WP_CLI::add_command( 'network-plugin-auditor plugins', [
			__NAMESPACE__ . '\\WPCLI\\Plugins_Command',
			'run',
		] );
	}
} );
