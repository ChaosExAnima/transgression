const codesText = document.getElementById('codes');

async function copyCodes() {
	if (!codesText || !codesText.textContent) {
		return;
	}
	await navigator.clipboard.writeText(codesText.textContent.trim());
	document.getElementById('result')?.classList.remove('hidden');
}

codesText?.addEventListener('click', copyCodes);
