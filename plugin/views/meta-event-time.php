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
		min="<?php echo esc_attr( wp_date( 'Y-m-d\TH:00' ) ); ?>"
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
		min="<?php echo esc_attr( wp_date( 'Y-m-d\TH:00' ) ); ?>"
		step="1800"
	/>
</p>
<p class="description">All times in <?php echo esc_html( wp_timezone_string() ); ?></p>
<script type="module">
	/** @type HTMLInputElement */
	const startEle = document.getElementById('trans_start_time');
	const endEle = document.getElementById('trans_end_time');
	document.addEventListener('load', update, {once: true});
	startEle.addEventListener('change', update);
	endEle.addEventListener('change', update);
	function update() {
		if (startEle.value) {
			endEle.setAttribute('min', startEle.value);
		}
		if (endEle.value) {
			startEle.setAttribute('max', endEle.value);
		}

	}
</script>
<style>
	.trans_event_time:invalid {
		color: #b32d2e;
	}
</style>
