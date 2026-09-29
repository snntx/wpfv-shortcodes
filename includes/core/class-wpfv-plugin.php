<?php
namespace WPFVariables\Core;

use WPFVariables\Admin\SettingsPage;
use WPFVariables\Fields\Types;
use WPFVariables\Frontend\Shortcodes;
use WPFVariables\Tracking\Ajax;
use WPFVariables\Tracking\Tracker;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static $instance;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		$store = new Store();
		$types = new Types();

		( new Shortcodes( $store, $types ) )->hook();
		( new Tracker( $store ) )->hook();
		( new Ajax( $store ) )->hook();

		if ( is_admin() ) {
			( new SettingsPage( $store, $types ) )->hook();
		}
	}
}
