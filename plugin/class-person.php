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

	public string $id; // We use emails for person comparison

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

		$this->id = $this->email();
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
	 * Returns true for approved attendees
	 *
	 * @return bool
	 */
	public function approved(): bool {
		if ( $this->application ) {
			return $this->application->post_status === Applications::STATUS_APPROVED;
		}
		return true;
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

		/** @var string[] */
		$meta_keys = [];
		$user_id = null;

		$query_flags = [
			'access' => false,
			'conf' => false,
			'email' => false,
			'extra' => false,
			'ig' => false,
			'status*' => false,
		];
		foreach ( array_keys( $query_flags ) as $query_key ) {
			// Gets the trimmed flag and saves it to the query
			$prefix = trim( $query_key, '*' );
			if ( $prefix !== $query_key ) {
				$query_flags[ $prefix ] = false;
				unset( $query_flags[ $query_key ] );
			}

			if ( str_starts_with( $query, "{$prefix}:" ) ) {
				$value = true;
				if ( str_ends_with( $query_key, '*' ) ) {
					preg_match( "/^{$prefix}:(\w+)/", $query, $matches );
					if ( count( $matches ) > 1 ) {
						$value = $matches[1];
					}
				}
				$query_flags[ $prefix ] = $value;
				$value = $value === true ? '' : $value;
				$query = preg_replace( "/^{$prefix}:{$value} ?/", '', $query );
			}
		}
		if ( ! $query ) {
			return [];
		}

		// Check by order ID
		if ( intval( $query ) > 0 ) {
			$order = wc_get_order( $query );
			$user_id = $order->get_user_id();
			// Next, check if this is an email
		} elseif ( is_email( $query ) ) {
			$user_id = email_exists( $query );
			$meta_keys[] = 'email';
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

		if ( $query_flags['ig'] || is_url( $query ) ) {
			$meta_keys[] = 'photo_url';
		} elseif ( $query_flags['conf'] ) {
			$meta_keys[] = 'conflicts';
		} elseif ( $query_flags['access'] ) {
			$meta_keys[] = 'accessibility';
		} elseif ( $query_flags['extra'] ) {
			$meta_keys[] = 'extra';
		} elseif ( $query_flags['email'] && ! in_array( 'email', $meta_keys, true ) ) {
			$meta_keys[] = 'email';
		}

		// Queries user names
		$user_query = new \WP_User_Query( [
			'search' => "{$query}*",
			'search_columns' => [ 'display_name' ],
			'role' => 'customer',
		] );
		foreach ( $user_query->get_results() as $user ) {
			$people = self::append_if_new( $people, $user );
		}

		// Now the application queries
		$default_query_args = [
			'post_type' => Applications::POST_TYPE,
			'post_status' => [ Applications::STATUS_DENIED, Applications::STATUS_APPROVED ],
			'update_post_term_cache' => false,
			'cache_results' => false,
			'no_found_rows' => true,
			'orderby' => 'date',
			'order' => 'desc',
		];
		if ( $query_flags['status'] ) {
			$default_query_args['post_status'] = $query_flags['status'];
		}

		// Meta query first
		if ( count( $meta_keys ) > 0 ) {
			$meta_query_array = [ 'relation' => 'OR' ];
			// phpcs:disable WordPress.DB.SlowDBQuery
			foreach ( $meta_keys as $meta_key ) {
				$meta_query_array[] = [
					'key' => $meta_key,
					'value' => $query,
					'compare' => 'LIKE',
				];
			}
			$meta_query = new \WP_Query( [
				...$default_query_args,
				'meta_query' => $meta_query_array,
			] );
			// phpcs:enable
			foreach ( $meta_query->get_posts() as $app ) {
				$people = self::append_if_new( $people, null, $app );
			}
		}

		// Then regular search
		$post_query = new \WP_Query( [
			...$default_query_args,
			's' => $query,
		] );
		foreach ( $post_query->get_posts() as $app ) {
			$people = self::append_if_new( $people, null, $app );
		}

		wp_cache_set( $query, $people, self::CACHE_GROUP, DAY_IN_SECONDS );

		return $people;
	}

	/**
	 * Appends a person if they aren't added yet
	 *
	 * @param array $people The array to modify
	 * @param WP_User|null $user User
	 * @param WP_Post|null $application Application
	 * @param WC_Customer|null $customer Woo customer
	 * @return array
	 */
	public static function append_if_new(
		array $people,
		?WP_User $user = null,
		?WP_Post $application = null,
		?WC_Customer $customer = null
	): array {
		$person = new self( $user, $application, $customer );
		foreach ( $people as $old_person ) {
			if ( $old_person->id === $person->id ) {
				return $people;
			}
		}
		$people[] = $person;
		return $people;
	}
}
