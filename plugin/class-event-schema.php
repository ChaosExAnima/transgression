<?php declare( strict_types=1 );

namespace Transgression;

use Yoast\WP\SEO\Config\Schema_IDs;
use Yoast\WP\SEO\Context\Meta_Tags_Context;

class Event_Schema {
	/**
	 * Initializes schema with filter.
	 * @return void
	 */
	public static function init(): void {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}
		add_filter( 'wpseo_schema_graph_pieces', function ( array $pieces, Meta_Tags_Context $context ): array {
			$pieces[] = new self( $context );
			return $pieces;
		}, 11, 2 );
	}

	public function __construct( public Meta_Tags_Context $context ) {}

	public function is_needed(): bool {
		return is_product() || is_shop();
	}

	/**
	 * Adds our Event piece of the graph.
	 *
	 * @return array Event Schema markup.
	 */
	public function generate(): array {
		if ( is_product() ) {
			return $this->get_event( $this->context->post->ID );
		} elseif ( is_shop() ) {
			global $posts;
			return [
				'@type' => 'ItemList',
				'url' => $this->context->permalink,
				'numberOfItems' => count( $posts ),
				'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
				'itemListElement' => array_map( [ $this, 'get_event' ], wp_list_pluck( $posts, 'ID' ) ),
			];
		}
		return [];
	}

	/**
	 * Gets event JSON-LD data
	 *
	 * @param int $post_id
	 * @return array
	 */
	protected function get_event( int $post_id ): array {
		$data = [
			// Info about the event
			'@type' => 'Event',
			'@id' => \YoastSEO()->meta->for_post( $post_id )->canonical . '#/event/' . $post_id,
			'name' => the_title_attribute( [ 'echo' => false ] ),
			'organizer' => $this->context->site_url . Schema_IDs::ORGANIZATION_HASH,
			'eventStatus' => 'http://schema.org/EventScheduled',
			'eventAttendanceMode' => 'http://schema.org/OfflineEventAttendanceMode',
			// The genders!
			'audience' => [
				'@type' => 'PeopleAudience',
				'audienceType' => 'Trans people',
				'requiredGender' => 'transgender or non-binary',
				'requiredMinAge' => 18,
			],
			// Place
			'location' => [
				'@type' => 'Place',
				'name' => 'NYC',
				'address' => [
					'@type' => 'PostalAddress',
					'addressLocality' => 'New York',
					'addressRegion' => 'NY',
					'addressCountry' => [
						'@type' => 'Country',
						'name' => 'US',
					],
				],
			],
		];

		// Add times
		$start_date = get_post_meta( $post_id, 'start_time' );
		if ( $start_date ) {
			$data['startDate'] = $start_date;
		}
		$end_date = get_post_meta( $post_id, 'end_time' );
		if ( $end_date ) {
			$data['endDate'] = $end_date;
		}

		// Add price info
		$product = wc_get_product( $post_id );
		$offers = [];
		if ( $product instanceof \WC_Product_Variable ) {
			/** @var \WC_Product_Variation $variation */
			foreach ( $product->get_available_variations( 'object' ) as $variation ) {
				$offers[] = $this->get_offer( $variation );
			}
		} elseif ( $product ) {
			$offers[] = $this->get_offer( $product );
		}
		if ( count( $offers ) > 0 ) {
			$data['offers'] = $offers;
		}

		return $data;
	}

	/**
	 * Gets the offer data from a product
	 *
	 * @param \WC_Product_Simple $product The product class
	 * @return array
	 */
	protected function get_offer( \WC_Product_Simple $product ): array {
		$offer = [
			'@type' => 'Offer',
			'name' => $product->get_name(),
			'availability' => 'http://schema.org/' . ( $product->is_in_stock() ? 'InStock' : 'OutOfStock' ),
			'hasAdultConsideration' => [ 'SexualContentConsideration' ],
			'price' => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ),
			'priceSpecification' => [
				'price' => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ),
				'priceCurrency' => get_woocommerce_currency(),
				'valueAddedTaxIncluded' => wc_prices_include_tax() ? 'true' : 'false',
			],
		];
		if ( $product instanceof \WC_Product_Variation ) {
			$offer['name'] = $product->get_attribute( 'tier' );
		}

		return $offer;
	}
}
