<?php
/**
 * Plugin Name: WPFVariables
 * Plugin URI:  https://www.kushanekanayake.me/
 * Description: Define reusable variables in the backend and print them anywhere with [wpfv-myvariable] shortcodes.
 * Version:     1.0.0
 * Author:      Kushan Ekanayake
 * Author URI:  https://www.kushanekanayake.me/
 * License:     GPL-2.0-or-later
 * Text Domain: wpf-variables
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

defined( 'ABSPATH' ) || exit;

define( 'WPFV_VERSION', '1.0.0' );
define( 'WPFV_FILE', __FILE__ );
define( 'WPFV_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFV_URL', plugin_dir_url( __FILE__ ) );

require_once WPFV_PATH . 'includes/bootstrap.php';
