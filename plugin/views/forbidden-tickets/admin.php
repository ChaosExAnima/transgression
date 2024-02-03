<?php declare( strict_types=1 );

/**
 * Template for showing ticket admin page
 * @var array $params
 */

namespace Transgression;

$codes = $params['codes'] ?? [];
?>
<style>
	code {
		display: block;
		padding: 1rem 2.5rem 1rem 1rem;
		border-radius: 0.5rem;
		margin: 1rem 0;
		max-width: 400px;
		overflow: hidden;
		text-overflow: ellipsis;
		position: relative;
		cursor: pointer;
	}

	code > .dashicons {
		position: absolute;
		right: 1rem;
		top: 1rem;
	}

	.copy-success {
		color: #00a32a;
	}
</style>
<code id="codes"><?php echo esc_textarea( implode( ',', $codes ) ); ?> <span class="dashicons dashicons-editor-paste-text"></span></code>
<p id="result" class="hidden copy-success">Successfully copied codes!</p>

<script>
	// Get the text field
	const codesText = document.getElementById("codes");
	async function copyCodes() {
		await navigator.clipboard.writeText(codesText.textContent.trim());
		document.getElementById('result').classList.remove('hidden');
	}
	codesText.addEventListener('click', copyCodes);
</script>
