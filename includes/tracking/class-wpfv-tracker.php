<?php
namespace WPFVariables\Tracking;

use WPFVariables\Admin\SettingsPage;
use WPFVariables\Core\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Loads tracker.js where content is being edited: the block/classic editor screens,
 * and the frontend of supported page builders.
 */
class Tracker {

	const BUILDER_PARAMS = [ 'et_fb', 'elementor-preview', 'fl_builder', 'ct_builder', 'bricks', 'brizy-edit', 'vc_editable' ];

	private $store;

	public function __construct( Store $store ) {
		$this->store = $store;
	}

	public function hook(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'front_assets' ] );
	}

	public function admin_assets( string $hook ): void {
		if ( in_array( $hook, [ 'post.php', 'post-new.php', 'site-editor.php' ], true ) ) {
			$this->enqueue();
		}
	}

	/** Extend with the wpfv_track_frontend filter for builders not listed above. */
	public function front_assets(): void {
		$active = (bool) apply_filters( 'wpfv_track_frontend', false );

		foreach ( self::BUILDER_PARAMS as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- presence check only, nothing is read or stored.
				$active = true;
			}
		}

		if ( $active ) {
			$this->enqueue();
		}
	}

	private function enqueue(): void {
		$interval = $this->store->interval();
		if ( ! $interval || ! is_user_logged_in() || ! current_user_can( Ajax::CAP ) ) {
			return;
		}

		wp_enqueue_style( 'wpfv-tracker', WPFV_URL . 'assets/css/tracker.css', [], WPFV_VERSION );
		wp_enqueue_script( 'wpfv-tracker', WPFV_URL . 'assets/js/tracker.js', [], WPFV_VERSION, true );

		$data = [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'action'   => Ajax::ACTION,
			'nonce'    => wp_create_nonce( Ajax::ACTION ),
			'interval' => $interval * 1000,
			'editUrl'  => SettingsPage::url(),
			'i18n'     => [
				'edit'   => __( 'Edit', 'wpf-variables' ),
				'create' => __( 'Create', 'wpf-variables' ),
			],
		];

		$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		wp_add_inline_script( 'wpfv-tracker', 'window.wpfvTracker = ' . $json . ';', 'before' );
	}
}
