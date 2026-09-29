<?php
namespace WPFVariables\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Field types: label, sanitize on save, print on the frontend.
 */
class Types {

	public function labels(): array {
		return [
			'text'     => __( 'Text', 'wpf-variables' ),
			'textarea' => __( 'Textarea', 'wpf-variables' ),
			'html'     => __( 'HTML (visual editor)', 'wpf-variables' ),
			'code'     => __( 'Code (CodeMirror)', 'wpf-variables' ),
			'image'    => __( 'Image', 'wpf-variables' ),
			'url'      => __( 'URL', 'wpf-variables' ),
			'number'   => __( 'Number', 'wpf-variables' ),
			'color'    => __( 'Color', 'wpf-variables' ),
			'boolean'  => __( 'Yes / No', 'wpf-variables' ),
		];
	}

	public function has( $type ): bool {
		return is_string( $type ) && isset( $this->labels()[ $type ] );
	}

	/** html and code are raw output, so they are only left unfiltered for users who hold unfiltered_html. */
	public function sanitize( string $type, $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'html':
			case 'code':
				return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
			case 'image':
				$id = absint( $value );
				return $id ? (string) $id : '';
			case 'url':
				return esc_url_raw( $value );
			case 'number':
				return is_numeric( trim( $value ) ) ? trim( $value ) : '';
			case 'color':
				return (string) sanitize_hex_color( $value );
			case 'boolean':
				return '1' === $value ? '1' : '0';
			default:
				return sanitize_text_field( $value );
		}
	}

	public function render( string $type, string $value ): string {
		switch ( $type ) {
			case 'textarea':
				return nl2br( esc_html( $value ) );
			case 'html':
				return wpautop( $value );
			case 'code':
				return $value;
			case 'image':
				return wp_get_attachment_image( (int) $value, 'full' );
			case 'url':
				return esc_url( $value );
			default:
				return esc_html( $value );
		}
	}
}
