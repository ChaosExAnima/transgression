<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

/** @var int */
$app_id = $params['id'] ?? 0;
/** @var Admin\Page */
$admin = $params['admin'];

$action_url = $admin->get_action_url( 'flag', (string) $app_id );

?>
<form action="<?php echo esc_url( $action_url ); ?>" method="POST" class="add-flag">
	<?php wp_nonce_field( "conflict-{$app_id}" ); ?>
	<span>Add new flag:</span>
	<input type="text" name="name" placeholder="Name" required data-1p-ignore />
	<label class="input">@
	<input
		type="text"
		name="instagram"
		placeholder="Instagram"
		class="plain"
		pattern="^[a-zA-Z0-9._]{3,30}$"
		data-1p-ignore
	/></label>
	<input type="email" name="email" placeholder="Email" data-1p-ignore />
	<?php submit_button( 'Add flag', 'primary', 'submit', false ); ?>
</form>
