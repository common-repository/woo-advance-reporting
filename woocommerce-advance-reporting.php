<?php
/*
Plugin Name: Woocommerce advance reporting
Plugin URI: 
Description: Woocommerce advance reporting is a comprehensive and the most complete reporting system of all past orders with graphic interactive representation. Data can be viewed starting from all years to all days in a month.
Version: 1.0.1
Author: Webnware
Author URI: 
License: GPL3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'WAR_VERSION', '1.0.1' );
define( 'WAR__MINIMUM_WP_VERSION', '3.2' );
define( 'WAR__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WAR__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAR_DELETE_LIMIT', 100000 );

require_once( WAR__PLUGIN_DIR . 'reporting.php' );

