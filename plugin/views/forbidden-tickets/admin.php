<?php declare( strict_types=1 );

/**
 * Template for showing ticket admin page
 * @var array $params
 */

namespace Transgression;

$codes = $params['codes'] ?? [];
?>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">Event Codes</th>
			<td>
				<div class="flex-row">
					<code id="codes"><?php echo esc_textarea( implode( ',', $codes ) ); ?> <span class="dashicons dashicons-editor-paste-text"></span></code>
					<p id="result" class="hidden copy-success">Successfully copied codes!</p>
				</div>
			</td>
		</tr>
	</tbody>
</table>

<script>
	// Get the text field
	const codesText = document.getElementById("codes");
	async function copyCodes() {
		await navigator.clipboard.writeText(codesText.textContent.trim());
		document.getElementById('result').classList.remove('hidden');
	}
	codesText.addEventListener('click', copyCodes);
</script>
