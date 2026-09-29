<?php
namespace WPFVariables\Tracking;

use WPFVariables\Core\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Answers the tracker's "are these variables defined?" polls.
 * Logged-in users only: POST, valid nonce, edit capability, whitelisted input.
 */
class Ajax {

	const ACTION    = 'wpfv_track';
	const CAP       = 'edit_posts';
	const MAX_SLUGS = 50;

	private $store;

	public function __construct( Store $store ) {
		$this->store = $store;
	}

	public function hook(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function handle(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method ) {
			wp_send_json_error( null, 405 );
		}

		check_ajax_referer( self::ACTION, 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( null, 403 );
		}

		$raw = isset( $_POST['slugs'] ) && is_array( $_POST['slugs'] ) ? array_slice( wp_unslash( $_POST['slugs'] ), 0, self::MAX_SLUGS ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each item is whitelisted by Store::clean_slug().
		$out = [];

		foreach ( $raw as $item ) {
			$slug = $this->store->clean_slug( $item );
			if ( '' === $slug ) {
				continue;
			}

			$row         = $this->store->get( $slug );
			$out[ $slug ] = [
				'exists'  => (bool) $row,
				'preview' => $row ? $this->preview( $row ) : '',
			];
		}

		wp_send_json_success( $out );
	}

	private function preview( array $row ): string {
		switch ( $row['type'] ) {
			case 'image':
				$file = get_attached_file( (int) $row['value'] );
				return $file ? basename( $file ) : '';
			case 'boolean':
				return '1' === $row['value'] ? 'Yes' : 'No';
			case 'html':
			case 'code':
				return wp_trim_words( wp_strip_all_tags( $row['value'] ), 8, '…' );
			default:
				return mb_strimwidth( wp_strip_all_tags( $row['value'] ), 0, 40, '…' );
		}
	}
}
