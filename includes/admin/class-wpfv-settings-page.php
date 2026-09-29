<?php
namespace WPFVariables\Admin;

use WPFVariables\Core\Store;
use WPFVariables\Fields\Types;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const SLUG = 'wpfv';
	const CAP  = 'manage_options';

	private $store;
	private $types;

	public function __construct( Store $store, Types $types ) {
		$this->store = $store;
		$this->types = $types;
	}

	/** Settings screen URL; with a slug the page opens (or creates) that variable. */
	public static function url( string $slug = '' ): string {
		$args = [ 'page' => self::SLUG ];
		if ( '' !== $slug ) {
			$args['wpfv_var'] = $slug;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public function hook(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_filter( 'option_page_capability_' . self::SLUG, [ $this, 'capability' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WPFV_FILE ), [ $this, 'action_links' ] );
	}

	public function capability(): string {
		return self::CAP;
	}

	public function menu(): void {
		add_menu_page( 'WPFVariables', 'WPFVariables', self::CAP, self::SLUG, [ $this, 'render' ], $this->icon(), 80 );
	}

	private function icon(): string {
		$svg = file_get_contents( WPFV_PATH . 'assets/img/icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		return $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-editor-code'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Variables', 'wpf-variables' ) . '</a>' );
		return $links;
	}

	public function register_settings(): void {
		register_setting( self::SLUG, Store::VARIABLES, [ 'sanitize_callback' => [ $this, 'sanitize_variables' ] ] );
		register_setting( self::SLUG, Store::SETTINGS, [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );
	}

	public function sanitize_variables( $input ): array {
		$clean = [];
		$seen  = [];

		if ( ! current_user_can( self::CAP ) ) {
			return $this->store->all();
		}

		foreach ( (array) $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$slug = $this->store->clean_slug( $row['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			if ( isset( $seen[ $slug ] ) ) {
				/* translators: %s: variable name */
				add_settings_error( self::SLUG, 'wpfv_duplicate_' . $slug, sprintf( __( 'Duplicate variable "%s" was skipped.', 'wpf-variables' ), $slug ) );
				continue;
			}
			$seen[ $slug ] = true;

			$type    = $this->types->has( $row['type'] ?? null ) ? $row['type'] : 'text';
			$clean[] = [
				'slug'  => $slug,
				'type'  => $type,
				'value' => $this->types->sanitize( $type, $row['value'] ?? '' ),
			];
		}

		return $clean;
	}

	public function sanitize_settings( $input ): array {
		$interval = is_array( $input ) && isset( $input['interval'] ) ? (int) $input['interval'] : 5;
		return [ 'interval' => max( 0, min( 300, $interval ) ) ];
	}

	public function assets( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_editor();
		$code = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );

		wp_enqueue_style( 'wpfv-admin', WPFV_URL . 'assets/css/admin.css', [], WPFV_VERSION );
		wp_enqueue_script( 'wpfv-admin', WPFV_URL . 'assets/js/admin.js', [ 'editor', 'code-editor', 'media-views' ], WPFV_VERSION, true );

		$rows = [];
		foreach ( $this->store->all() as $row ) {
			$row['preview'] = 'image' === $row['type'] ? (string) wp_get_attachment_image_url( (int) $row['value'], 'thumbnail' ) : '';
			$rows[]         = $row;
		}

		$focus = isset( $_GET['wpfv_var'] ) ? $this->store->clean_slug( wp_unslash( $_GET['wpfv_var'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$data = [
			'rows'         => $rows,
			'types'        => $this->types->labels(),
			'focus'        => $focus,
			'codeSettings' => $code,
			'i18n'         => [
				'chooseImage' => __( 'Choose image', 'wpf-variables' ),
				'select'      => __( 'Select image', 'wpf-variables' ),
				'remove'      => __( 'Remove', 'wpf-variables' ),
				'copy'        => __( 'Copy', 'wpf-variables' ),
				'copied'      => __( 'Copied', 'wpf-variables' ),
				'yes'         => __( 'Yes', 'wpf-variables' ),
				'no'          => __( 'No', 'wpf-variables' ),
				'empty'       => __( 'No variables yet. Add your first one below.', 'wpf-variables' ),
			],
		];

		$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		wp_add_inline_script( 'wpfv-admin', 'window.wpfvAdmin = ' . $json . ';', 'before' );
	}

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpf-variables' ), 403 );
		}
		?>
		<div class="wrap wpfv-wrap">
			<h1>WPFVariables</h1>
			<?php settings_errors(); ?>
			<p><?php esc_html_e( 'Create a variable, then print it anywhere (posts, pages, widgets, page builders) with a shortcode like', 'wpf-variables' ); ?> <code>[wpfv-myvariable]</code>.</p>

			<form method="post" action="options.php" id="wpfv-form">
				<?php settings_fields( self::SLUG ); ?>
				<input type="hidden" name="<?php echo esc_attr( Store::VARIABLES ); ?>[]" value="">

				<div id="wpfv-rows"></div>
				<p><button type="button" class="button button-secondary" id="wpfv-add">+ <?php esc_html_e( 'Add variable', 'wpf-variables' ); ?></button></p>

				<h2><?php esc_html_e( 'Live editor tracking', 'wpf-variables' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpfv-interval"><?php esc_html_e( 'Scan interval (seconds)', 'wpf-variables' ); ?></label></th>
						<td>
							<input type="number" id="wpfv-interval" class="small-text" min="0" max="300" name="<?php echo esc_attr( Store::SETTINGS ); ?>[interval]" value="<?php echo esc_attr( $this->store->interval() ); ?>">
							<p class="description"><?php esc_html_e( 'While editing, [wpfv-…] shortcodes are detected and get an edit/create tooltip. Lower is faster, higher is lighter on the server. 0 turns tracking off.', 'wpf-variables' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
