<?php declare( strict_types = 1 );

namespace Transgression;

use Error;
use Transgression\Modules\Applications;
use WC_Customer;
use WP_Post;
use WP_User;

/**
 * Person class, used to find people by various means
 */
class Person {
	public const CACHE_GROUP = PLUGIN_SLUG . '_person_search';

	public function __construct(
		public ?WP_User $user = null,
		public ?WP_Post $application = null,
		public ?WC_Customer $customer = null
	) {
		if ( ! $user && ! $application && ! $customer ) {
			throw new Error( 'Must specify something to find person from' );
		}

		if ( ! $user ) {
			if ( $application?->created_user ) {
				$user_id = $application->created_user;
			} elseif ( $customer ) {
				$user_id = $customer->get_id();
			}
			$this->user = falsey_to_null( get_user_by( 'id', $user_id ?? false ) );
		}

		if ( ! $application && $this->user?->application ) {
			$this->application = get_post( $this->user?->application );
		}

		if ( ! $customer && $this->user ) {
			$this->customer = new WC_Customer( $this->user->ID );
		}

		if ( ! $this->user && ! $this->application ) {
			throw new Error( 'Could not find person' );
		}
	}

	/**
	 * Gets the name
	 *
	 * @return string
	 */
	public function name(): string {
		if ( $this->user ) {
			return $this->user->display_name;
		}
		return $this->application->post_title;
	}

	/**
	 * Gets pronouns, if any
	 *
	 * @return string|null
	 */
	public function pronouns(): ?string {
		if ( $this->user?->pronouns ) {
			return $this->user->pronouns;
		}
		return $this->application?->pronouns;
	}

	/**
	 * Gets the email
	 *
	 * @return string
	 */
	public function email(): string {
		if ( $this->user ) {
			return $this->user->user_email;
		}
		return $this->application->email;
	}

	/**
	 * Returns the image path, if any
	 *
	 * @return string|null
	 */
	public function image_url( int $width = 100 ): ?string {
		$url = $this->application?->photo_img;
		if ( ! $url ) {
			return null;
		}
		return jetpack_photon_url( $url, [ 'w' => $width ] );
	}

	/**
	 * Gets all WooCommerce orders
	 *
	 * @return \WC_Order[]
	 */
	public function orders(): array {
		if ( ! $this->user ) {
			return [];
		}
		return wc_get_orders( [ 'customer' => $this->user->user_email ] );
	}

	/**
	 * Gets a link to all customer orders
	 *
	 * @return string|null
	 */
	public function orders_link(): ?string {
		if ( ! $this->customer ) {
			return null;
		}
		return add_query_arg( [
			'post_type' => 'shop_order',
			'_customer_user' => $this->customer->get_id(),
		], admin_url( 'edit.php' ) );
	}

	/**
	 * Performs a search by query string
	 *
	 * @param string $query The query
	 * @param bool $prefer_one If one result looks likely, return just that
	 * @return self[]
	 */
	public static function search( string $query, bool $prefer_one = true ): array {
		$query = trim( $query );
		$cached = wp_cache_get( $query, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$meta_keys = [];
		$user_id = null;

		// Check by order ID
		if ( intval( $query ) > 0 ) {
			$order = wc_get_order( $query );
			$user_id = $order->get_user_id();
			// Next, check if this is an email
		} elseif ( is_email( $query ) ) {
			$user_id = email_exists( $query );
			$meta_keys[] = 'email';
			// Check Instagram or other apps
		} elseif ( str_starts_with( $query, '@' ) || is_url( $query ) ) {
			$meta_keys[] = 'photo_url';
		}

		$people = [];

		// Get via user ID
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$people[] = new self( $user );
				if ( $prefer_one ) {
					return $people;
				}
			}
		}

		// Queries user names
		$user_query = new \WP_User_Query( [
			'search' => "{$query}*",
			'search_columns' => [ 'display_name' ],
			'role' => 'customer',
		] );
		foreach ( $user_query->get_results() as $user ) {
			$people[] = new self( $user );
		}

		// Now the application queries
		$default_query_args = [
			'post_type' => Applications::POST_TYPE,
			'post_status' => [ Applications::STATUS_DENIED ], // Not approved as those are users
			'update_post_term_cache' => false,
			'cache_results' => false,
			'no_found_rows' => true,
			'orderby' => 'date',
			'order' => 'desc',
		];

		// Meta query first
		if ( count( $meta_keys ) > 0 ) {
			$meta_query_array = [];
			// phpcs:disable WordPress.DB.SlowDBQuery
			foreach ( $meta_keys as $meta_key ) {
				$meta_query[] = [
					'meta_key' => $meta_key,
					'meta_value' => $query,
				];
			}
			$meta_query = new \WP_Query( [
				...$default_query_args,
				'meta_query' => $meta_query_array,
			] );
			// phpcs:enable
			foreach ( $meta_query->get_posts() as $app ) {
				$people[] = new self( null, $app );
			}
		}

		// Then regular search
		$post_query = new \WP_Query( [
			...$default_query_args,
			's' => $query,
		] );
		foreach ( $post_query->get_posts() as $app ) {
			$people[] = new self( null, $app );
		}

		wp_cache_set( $query, $people, self::CACHE_GROUP, DAY_IN_SECONDS );

		return $people;
	}
}
