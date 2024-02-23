<?php declare( strict_types = 1 );
/**
 * Template for attendance header
 * @var array $params
 */

namespace Transgression;

/** @var int current attachment id */
$attachment_id = $params['attachment_id'];
/** @var \WP_Query list of all attachments */
$attachments = $params['attachments'];
/** @var string search string */
$search = $params['search'];
?>
<form id="header-form" class="header" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get">
	<input type="hidden" name="page" value="transgression_attendance" />
	<?php wp_nonce_field( prefix( 'attendance' ) ); ?>
	<p class="attachment-wrapper">
		<label for="attachment">Event</label>
		<select class="widefat" name="attachment_id" id="attachment">
			<?php foreach ( $attachments->posts as $attachment ) : ?>
				<option value="<?php echo absint( $attachment->ID ); ?>" <?php selected( $attachment_id, $attachment->ID ); ?>>
					<?php echo esc_html( get_the_title( $attachment ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button button-primary">Update</button>
		<button type="button" onclick="window.print()" class="button button-print">Print</button>
		<label for="percentage">Attending</label>
		<meter id="percentage" value="0" min="0">0 / 0</meter>
	</p>
	<p class="search-wrapper">
		<input
			id="search"
			name="search"
			type="search"
			class="widefat"
			value="<?php echo esc_attr( $search ); ?>"
			aria-label='Search input for attendees'
			spellcheck='false'
			incremental
			placeholder='Search for order id, name, or email'
			autocomplete="name email"
		/>
		<button type="submit" class="button button-primary">
			<span class="dashicons dashicons-search"></span>
		</button>
	</p>
</form>
