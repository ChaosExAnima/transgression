<?php declare( strict_types = 1 );

namespace Transgression;

use Error;
use Transgression\Modules\Applications;
use Transgression\Modules\ForbiddenTickets;
use WP_Post;
use WP_User;
use WP_User_Query;

/**
 * Person class, used to find people by various means
 */
class Person {
	public const CACHE_SEARCH = PLUGIN_SLUG . '_person_search';
	public const CACHE_VACCINATED = PLUGIN_SLUG . '_person_vaxxed';

	public string $id; // We use emails for person comparison

	public function __construct(
		public ?WP_User $user = null,
		public ?WP_Post $application = null
	) {
		if ( ! $user && ! $application ) {
			throw new Error( 'Must specify something to find person from' );
		}

		if ( ! $user ) {
			if ( $application?->created_user ) {
				$user_id = $application->created_user;
			}
			$this->user = falsey_to_null( get_user_by( 'id', $user_id ?? false ) );
		}

		if ( ! $application && $this->user?->application ) {
			$this->application = get_post( $this->user?->application );
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
	 * Returns true if someone is vaccinated
	 *
	 * @return boolean
	 */
	public function vaccinated( ?bool $set = null ): bool {
		if ( ! $this->user ) {
			return false;
		}
		if ( null !== $set ) {
			update_user_meta( $this->user_id(), 'vaccinated', (int) $set );
			return $set;
		}
		return (bool) $this->user->vaccinated;
	}

	/**
	 * Gets the user code
	 *
	 * @return string
	 */
	public function code(): string {
		return get_user_meta( $this->user_id(), ForbiddenTickets::USER_CODE_KEY, true ) ?? '';
	}

	/**
	 * Gets the user ID
	 *
	 * @return integer|null
	 */
	public function user_id(): ?int {
		return $this->user?->ID;
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
		$cached = wp_cache_get( $query, self::CACHE_SEARCH );
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

		if ( is_email( $query ) ) {
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

		wp_cache_set( $query, $people, self::CACHE_SEARCH, DAY_IN_SECONDS );

		return $people;
	}

	/**
	 * Gets a person from a user ID
	 *
	 * @param int $user_id User ID
	 * @return self
	 */
	public static function from_user_id( int $user_id ): self {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			throw new Error( 'Could not find user' );
		}
		return new self( $user );
	}

	/**
	 * Appends a person if they aren't added yet
	 *
	 * @param array $people The array to modify
	 * @param WP_User|null $user User
	 * @param WP_Post|null $application Application
	 * @return array
	 */
	public static function append_if_new(
		array $people,
		?WP_User $user = null,
		?WP_Post $application = null,
	): array {
		$person = new self( $user, $application );
		foreach ( $people as $old_person ) {
			if ( $old_person->id === $person->id ) {
				return $people;
			}
		}
		$people[] = $person;
		return $people;
	}
}
