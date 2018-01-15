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

require_once __DIR__ . '/inc/class-network-plugin-auditor.php';

/**
 * Kick it off.
 */
add_action( 'plugins_loaded', function() {
	$plugin = NetworkPluginAuditor::get_instance();
	$plugin->init();
} );
