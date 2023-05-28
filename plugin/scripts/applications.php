<?php declare( strict_types = 1 );

namespace Transgression\Scripts;

use Transgression\Modules\Applications;
use WP_CLI;
use WP_Query;

class Applications_Command {
	protected array $records = [];

	/**
	 * Fixes applications.
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>...]
	 * : Application IDs to fix
	 *
	 * [--all]
	 * : Operates on all applications
	 *
	 * [--dry-run]
	 * : Does a dry run
	 *
	 * ## Examples
	 *
	 * trans apps fix --all
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function fix( array $args, array $assoc_args ): void {
		if ( ! count( $args ) && empty( $assoc_args['all'] ) ) {
			WP_CLI::error( 'Requires application IDs or --all' );
		}
		$app_ids = [];
		foreach ( $args as $raw_arg ) {
			if ( ! is_numeric( $raw_arg ) ) {
				WP_CLI::error( "Could not parse ID {$raw_arg}" );
			}
			$app_ids[] = absint( $raw_arg );
		}

		$dry = ! empty( $assoc_args['dry-run'] );
		if ( ! $dry ) {
			WP_CLI::warning( 'Wet run' );
		}

		$updated = 0;
		$skipped = 0;
		$errors = 0;

		$query = new WP_Query( [
			'nopaging' => true,
			'orderby' => 'ID',
			'post__in' => $app_ids,
			'post_status' => [ 'pending', Applications::STATUS_APPROVED ],
			'post_type' => Applications::POST_TYPE,
		] );

		if ( ! $query->post_count ) {
			WP_CLI::error( 'No applications found' );
		}

		$ids = wp_list_pluck( $query->posts, 'ID' );
		$this->load_records( $ids );

		WP_CLI::line( "Found {$query->post_count} applications" );
		while ( $query->have_posts() ) {
			$query->the_post();
			$app = get_post();

			$record = $this->records[ $app->ID ] ?? null;

			$fields = [
				'associates' => [
					'associates' => false,
					'are_you_going_to_be_there_with_any' => false,
				],
				'conflicts' => [
					'are_there_any_specific_people_whos' => false,
					'conflicts' => false,
					'warnings' => true,
				],
				'extra' => [
					'anything_else_you\'d_like_us_to_kno' => false,
					'extra' => false,
				],
				'identity' => [
					'identify' => false,
				],
				'referrer' => [
					'source' => false,
				],
			];

			$changed = 0;
			foreach ( $fields as $meta_key => $sources ) {
				$value = '';
				foreach ( $sources as $source_key => $is_meta ) {
					if ( ! $is_meta && $record && isset( $record[ $source_key ] ) ) {
						$value = $record[ $source_key ];
						break 1;
					} elseif ( $is_meta && $app->{$source_key} ) {
						$value = $app->{$source_key};
						break 1;
					}
				}

				$value = trim( wp_unslash( $value ) );
				if ( ! $value || $value === $app->{$meta_key} ) {
					continue;
				}
				++$changed;

				$result = true;
				if ( ! $dry ) {
					$result = update_post_meta( $app->ID, $meta_key, $value );
				} elseif ( ! $app->{$meta_key} ) {
					WP_CLI::line( "Dry run: Setting {$meta_key} to '{$value}'" );
				} else {
					WP_CLI::line( "Dry run: Changed {$meta_key} from '{$app->{$meta_key}}' to '{$value}'" );
				}

				if ( ! $result ) {
					WP_CLI::warning( "Error updating {$meta_key} to '{$value}' from '{$app->{$meta_key}}' for {$app->post_title} ({$app->ID})" );
					++$errors;
				}
			}

			if ( $changed ) {
				++$updated;
				WP_CLI::line( "Updated {$changed} fields for {$app->post_title} ({$app->ID})" );
			} else {
				++$skipped;
			}
		}

		WP_CLI\Utils\report_batch_operation_results(
			'application',
			'update',
			$query->post_count,
			$updated,
			$errors,
			$skipped
		);
	}

	/**
	 * Loads Jetform records from post IDs
	 *
	 * @param array $post_ids Array of post IDs
	 * @return void
	 */
	protected function load_records( array $post_ids ): void {
		global $wpdb;

		$where_in = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		// phpcs:disable WordPress.DB
		$query = $wpdb->prepare(
			"SELECT * FROM wp_27ppk3_jet_fb_records_fields WHERE record_id IN (
				SELECT record_id FROM wp_27ppk3_jet_fb_records_fields
					WHERE field_name = 'inserted_post_id' AND field_value IN ({$where_in})
			)",
			...$post_ids
		);
		$results = $wpdb->get_results( $query );
		// phpcs:enable

		$records = [];
		$post_record_lookup = [];
		foreach ( $results as $row ) {
			if ( empty( $records[ $row->record_id ] ) ) {
				$records[ $row->record_id ] = [];
			}
			if ( $row->field_name === 'inserted_post_id' ) {
				$post_record_lookup[ absint( $row->field_value ) ] = $row->record_id;
			}
			$records[ $row->record_id ][ $row->field_name ] = $row->field_value;
		}

		foreach ( $post_ids as $post_id ) {
			if ( isset( $post_record_lookup[ $post_id ] ) ) {
				$this->records[ $post_id ] = $records[ $post_record_lookup[ $post_id ] ];
			}
		}
	}
}

WP_CLI::add_command( 'trans apps', __NAMESPACE__ . '\\Applications_Command' );
