<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

/** @var int */
$post_id = $params['post_id'];
/** @var ?string */
$start_time = $params['start_time'];
/** @var ?string */
$end_time = $params['end_time'];

wp_nonce_field( "event-time-{$post_id}", '_trans_event_nonce' );
?>
<p>
	<label for="trans_start_time">Start time</label>
	<input
		type="datetime-local"
		class="widefat trans_event_time"
		name="start_time"
		id="trans_start_time"
		value="<?php echo esc_attr( substr( $start_time, 0, -6 ) ); ?>"
		step="1800"
	/>
</p>
<p>
	<label for="trans_end_time">End time</label>
	<input
		type="datetime-local"
		class="widefat trans_event_time"
		name="end_time"
		id="trans_end_time"
		value="<?php echo esc_attr( substr( $end_time, 0, -6 ) ); ?>"
		step="1800"
	/>
</p>
<p class="description">All times in <?php echo esc_html( wp_timezone_string() ); ?></p>
<style>
	.trans_event_time:invalid {
		color: #b32d2e;
	}
</style>
