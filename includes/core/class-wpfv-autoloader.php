<?php
namespace WPFVariables\Core;

defined( 'ABSPATH' ) || exit;

/**
 * WPFVariables\Admin\SettingsPage -> includes/admin/class-wpfv-settings-page.php
 */
class Autoloader {

	const ROOT = 'WPFVariables\\';

	public static function register(): void {
		spl_autoload_register( [ __CLASS__, 'load' ] );
	}

	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::ROOT ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class, strlen( self::ROOT ) ) );
		$name  = array_pop( $parts );
		$name  = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $name ) );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = WPFV_PATH . 'includes/' . $dir . 'class-wpfv-' . $name . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
