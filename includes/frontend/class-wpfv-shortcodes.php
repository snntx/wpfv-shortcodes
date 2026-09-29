<?php
namespace WPFVariables\Frontend;

use WPFVariables\Admin\SettingsPage;
use WPFVariables\Core\Store;
use WPFVariables\Fields\Types;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	private $store;
	private $types;
	private $depth = 0;

	public function __construct( Store $store, Types $types ) {
		$this->store = $store;
		$this->types = $types;
	}

	public function hook(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'the_content', [ $this, 'flag_unknown' ], 12 );
		add_filter( 'widget_text_content', [ $this, 'flag_unknown' ], 12 );
		add_filter( 'widget_block_content', [ $this, 'flag_unknown' ], 12 );
	}

	/** One shortcode per variable, so core and page builders resolve them natively and the output caches like any other content. */
	public function register(): void {
		foreach ( $this->store->all() as $row ) {
			add_shortcode( 'wpfv-' . $row['slug'], [ $this, 'render' ] );
		}
	}

	public function render( $atts, $content, $tag ): string {
		$row = $this->store->get( substr( $tag, 5 ) );
		if ( ! $row ) {
			return '';
		}

		$out = $this->types->render( $row['type'], $row['value'] );

		if ( 'html' === $row['type'] && $this->depth < 3 ) {
			++$this->depth;
			$out = do_shortcode( $out );
			--$this->depth;
		}

		return $out;
	}

	/**
	 * Any [wpfv-…] still present after do_shortcode is undefined. Admins get a link
	 * to create it; everyone else sees the shortcode untouched.
	 */
	public function flag_unknown( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, '[wpfv-' ) || ! current_user_can( 'manage_options' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/\[wpfv-([a-z0-9][a-z0-9_-]*)\]/',
			static function ( $m ) {
				return sprintf(
					'<a href="%s" target="_blank" rel="noopener" style="background:#b32d2e;color:#fff;padding:1px 6px;border-radius:3px;font:600 12px/1.6 monospace;text-decoration:none">%s</a>',
					esc_url( SettingsPage::url( $m[1] ) ),
					esc_html( sprintf( '[wpfv-%s] is not defined, click to create it', $m[1] ) )
				);
			},
			$html
		);
	}
}
