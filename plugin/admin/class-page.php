<?php declare( strict_types=1 );

namespace Transgression\Admin;

use WP_Screen;

use function Transgression\get_asset_url;
use function Transgression\prefix;

use const Transgression\PLUGIN_VERSION;

class Page {
	protected string $page_slug = '';

	/** @var string[] */
	protected array $actions = [];

	protected string $page_hook = '';

	protected string $description = '';

	protected string $menu_label;

	/** @var string[] */
	protected array $styles = [];

	/** @var string[] */
	protected array $scripts = [];

	/**
	 * Creates admin page
	 *
	 * @param string $slug The page slug
	 * @param string $label Page title
	 * @param string|null $menu_label Menu label
	 * @param string $permission Access permission
	 */
	public function __construct(
		string $slug,
		protected string $label,
		?string $menu_label = null,
		protected string $permission = 'manage_options',
	) {
		$this->page_slug = prefix( $slug );
		$this->menu_label = $menu_label ?? $label;
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'current_screen', [ $this, 'do_screen_actions' ] );
	}

	/**
	 * Makes the admin page a top-level page
	 *
	 * @param string $icon Icon
	 * @param int|null $position Page position
	 * @return self
	 */
	public function as_page( string $icon, ?int $position = null ): self {
		$callback = function () use ( $icon, $position ) {
			$admin_page = add_menu_page(
				$this->label,
				$this->menu_label,
				$this->permission,
				$this->page_slug,
				[ $this, 'render_page' ],
				$icon,
				$position
			);
			if ( $admin_page ) {
				$this->page_hook = $admin_page;
			}
		};
		add_action( 'admin_menu', $callback );
		return $this;
	}

	/**
	 * Makes the admin page a subpage
	 *
	 * @param string $parent_page Parent page slug
	 * @param int|null $position Page position
	 * @return self
	 */
	public function as_subpage( string $parent_page, ?int $position = null ): self {
		$callback = function () use ( $parent_page, $position ) {
			$admin_page = add_submenu_page(
				$parent_page,
				$this->label,
				$this->menu_label,
				$this->permission,
				$this->page_slug,
				[ $this, 'render_page' ],
				$position
			);
			if ( $admin_page ) {
				$this->page_hook = $admin_page;
			}
		};
		add_action( 'admin_menu', $callback );
		return $this;
	}

	/**
	 * Makes the admin page a subpage of a post type
	 *
	 * @param string $post_type The post type slug
	 * @param int|null $position Page position
	 * @return self
	 */
	public function as_post_subpage( string $post_type, ?int $position = null ): self {
		return $this->as_subpage( "edit.php?post_type={$post_type}", $position );
	}

	/**
	 * Sets the page description
	 *
	 * @param string $description
	 * @return self
	 */
	public function with_description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Sets the render callback
	 *
	 * @param callable $callback
	 * @param int $priority
	 * @return self
	 */
	public function add_render_callback( callable $callback, int $priority = 10 ): self {
		add_action( "{$this->page_slug}_render", $callback, $priority );
		return $this;
	}

	/**
	 * Sets a screen callback
	 *
	 * @param callable $callback
	 * @param integer $priority
	 * @return self
	 */
	public function add_screen_callback( callable $callback, int $priority = 10 ): self {
		add_action( "{$this->page_slug}_screen_loaded", $callback, $priority );
		return $this;
	}

	/**
	 * Adds an action on on page load
	 *
	 * @param string $key Action key to check in request object
	 * @param callable $callback Callback to run when set
	 * @return Admin
	 */
	public function add_action( string $key, callable $callback ): self {
		$this->actions[] = $key;
		add_action( "{$this->page_slug}_action_{$key}", $callback, 10, 2 );
		return $this;
	}

	/**
	 * Adds a stylesheet from the assets directory
	 *
	 * @param string $name The name of the file without the extension
	 * @return self
	 */
	public function add_style( string $name ): self {
		$this->styles[] = $name;
		return $this;
	}

	/**
	 * Adds a script from the assets directory
	 *
	 * @param string $name The name of the file without the extension
	 * @param array $deps Script dependencies
	 * @param array $params Script localization data
	 * @return self
	 */
	public function add_script( string $name, array $deps = [], array $params = [] ): self {
		$this->scripts[ $name ] = [
			'deps' => $deps,
			'params' => $params,
		];
		return $this;
	}

	/**
	 * Gets the page URL based off of the page hook
	 *
	 * @param array $params
	 * @return string
	 */
	public function get_url( array $params = [] ): string {
		$screen = WP_Screen::get( $this->page_hook );
		$params['page'] = $this->page_slug;
		return add_query_arg( $params, admin_url( $screen->parent_file ) );
	}

	/**
	 * Gets the page URL with an action
	 *
	 * @param string $key
	 * @param string $value
	 * @param array $params Additional parameters to add
	 * @return string
	 */
	public function get_action_url( string $key, string $value, array $params = [] ): string {
		if ( in_array( $key, $this->actions, true ) ) {
			$params[ $key ] = $value;
		}
		return $this->get_url( $params );
	}

	/**
	 * Redirects to action with given key
	 *
	 * @param string $key
	 * @param string $value
	 * @param array $params Additional parameters to add
	 * @return void
	 */
	public function action_redirect( string $key, string $value, array $params = [] ) {
		if ( ! in_array( $key, $this->actions, true ) ) {
			return;
		}
		wp_safe_redirect(
			$this->get_action_url( $key, $value, $params )
		);
		exit;
	}

	/**
	 * Adds a message
	 *
	 * @param string $message The message html
	 * @param string $type Type of message. One of notice, error, success, or warning
	 * @return void
	 */
	public function add_message( string $message, string $type = 'error', array $holders = [] ) {
		add_settings_error(
			"{$this->page_slug}_messages",
			sanitize_title( substr( $message, 0, 10 ) ),
			sprintf( $message, ...$holders ),
			$type
		);
	}

	/**
	 * Registers a message to appear
	 *
	 * @param string $key The message key
	 * @param string $message Message text
	 * @param string $type Type of message- success, notice, or error
	 * @return self
	 */
	public function register_message(
		string $key,
		string $message,
		string $type = 'error',
		array $holders = [],
	): self {
		$callback = function ( string $action ) use ( $key, $message, $type, $holders ) {
			if ( $action === $key ) {
				$this->add_message( $message, $type, $holders );
			}
		};
		add_action( "{$this->page_slug}_action_messages", $callback, 10, 2 );
		if ( ! in_array( 'messages', $this->actions, true ) ) {
			$this->actions[] = 'messages';
		}
		return $this;
	}

	/**
	 * Calls hook whenever the screen is loaded
	 *
	 * @param \WP_Screen $screen
	 * @return void
	 */
	public function do_screen_actions( \WP_Screen $screen ): void {
		if ( $this->page_hook && $screen->id === $this->page_hook ) {
			do_action( "{$this->page_slug}_screen_loaded", $this );
			add_filter( 'removable_query_args', [ $this, 'remove_messages_arg' ] );
		}
	}

	/**
	 * Removes the messages arg for the current page.
	 *
	 * @param array $args
	 * @return array
	 */
	public function remove_messages_arg( array $args ): array {
		$args[] = 'messages';
		return $args;
	}

	/**
	 * Registers the styles if the correct page is loaded
	 *
	 * @param string $hook The page hook
	 * @return void
	 */
	public function register_assets( string $hook ): void {
		// Check page hook
		if ( $this->page_hook !== $hook ) {
			return;
		}
		foreach ( $this->styles as $name ) {
			wp_enqueue_style(
				prefix( $name ),
				get_asset_url( "{$name}.css" ),
				[],
				PLUGIN_VERSION
			);
		}
		foreach ( $this->scripts as $name => $args ) {
			/** @var array $deps
			 * @var array $params */
			[ 'deps' => $deps, 'params' => $params ] = $args;
			wp_enqueue_script(
				prefix( $name ),
				get_asset_url( "{$name}.js" ),
				$deps,
				PLUGIN_VERSION,
				true
			);
			if ( count( $params ) > 0 ) {
				wp_localize_script(
					prefix( $name ),
					"{$name}Data",
					$params
				);
			}
		}
	}

	/**
	 * Redirects to show a message
	 *
	 * @param string $key
	 * @param array $params
	 * @return void
	 */
	public function redirect_message( string $key, array $params = [] ): void {
		$this->action_redirect( 'messages', $key, $params );
	}

	/**
	 * Renders the page
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( $this->permission ) ) {
			wp_die();
		}

		foreach ( $this->actions as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_REQUEST[ $key ] ) ) {
				do_action(
					"{$this->page_slug}_action_{$key}",
					// phpcs:ignore WordPress.Security.NonceVerification
					sanitize_textarea_field( wp_unslash( $_REQUEST[ $key ] ) ),
					$this
				);
			}
		}

		settings_errors( "{$this->page_slug}_messages" );

		printf(
			'<div class="wrap"><h1>%s</h1>',
			esc_html( get_admin_page_title() )
		);
		if ( $this->description ) {
			printf(
				'<p>%s</p>',
				wp_kses_post( $this->description )
			);
		}
		do_action( "{$this->page_slug}_render", $this );
		echo '<div class="clear"></div>';
	}
}
