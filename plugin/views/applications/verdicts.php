<?php declare( strict_types=1 );

namespace Transgression;

/** @var array */
$verdicts = $params['verdicts'];

$yes_link = $params['yes_link'];
$no_link = $params['no_link'];
$finalize_link = $params['finalize_link'];

if ( count( $verdicts ) > 0 ) :
?>
<ul>
	<?php foreach ( $verdicts as $verdict ) : ?>
		<li>
			<?php
			printf(
				'%s - %s, ',
				$verdict['approved'] === true ? '✅' : '❌',
				esc_html( $user->display_name ),
			);
			render_time( $verdict['date'], true );
			?>
		</li>
	<?php endforeach; ?>
</ul>
<?php else : ?>
	<p><em>No verdicts yet</em></p>
<?php endif; ?>

<hr />
<p>
	<a href="<?php echo esc_url( $yes_link ); ?>" class="button button-primary">Approve</a>
	&nbsp;
	<a href="<?php echo esc_url( $no_link ); ?>" class="button button-warning">Reject</a>
</p>

<p>
	<?php if ( count( $verdicts ) > 0 ) : ?>
		<a href="<?php echo esc_url( $finalize_link ); ?>" class="button button-primary">Finalize Verdict</a>
	<?php else : ?>
		<button class="button" disabled>Finalize Verdict</button>
	<?php endif; ?>
</p>
