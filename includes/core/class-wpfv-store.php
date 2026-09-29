<?php
namespace WPFVariables\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reads variables and plugin settings from the options table.
 * Variables are an ordered list of [slug, type, value] rows.
 */
class Store {

	const VARIABLES = 'wpfv_variables';
	const SETTINGS  = 'wpfv_settings';

	private $index;

	public function all(): array {
		$rows = get_option( self::VARIABLES, [] );
		return is_array( $rows ) ? $rows : [];
	}

	public function get( string $slug ): ?array {
		if ( null === $this->index ) {
			$this->index = [];
			foreach ( $this->all() as $row ) {
				$this->index[ (string) $row['slug'] ] = $row;
			}
		}
		return $this->index[ $slug ] ?? null;
	}

	/** Seconds between editor scans. 0 disables live tracking. */
	public function interval(): int {
		$settings = get_option( self::SETTINGS, [] );
		$interval = isset( $settings['interval'] ) ? (int) $settings['interval'] : 5;
		return max( 0, min( 300, $interval ) );
	}

	public function clean_slug( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = strtolower( (string) $value );
		$value = preg_replace( '/^\[?wpfv-/', '', $value );
		$value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );
		return substr( trim( $value, '-_' ), 0, 60 );
	}
}
