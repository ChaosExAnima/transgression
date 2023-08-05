const actions =
	document.querySelectorAll<HTMLButtonElement>('.actions > button');
for (const button of actions) {
	button.addEventListener('click', handleAction);
}

function handleAction(event: MouseEvent) {
	event.preventDefault();
	const button = event.currentTarget;
	if (!(button instanceof HTMLButtonElement)) {
		return;
	}
	if (button.classList.contains('resolve-action')) {
		if (button.dataset.url && confirm('Resolve this conflict?')) {
			location.assign(button.dataset.url);
		}
	} else if (button.classList.contains('comment-action')) {
		const rowId = button.getAttribute('aria-controls');
		if (!rowId) {
			return;
		}
		const commentsRow = document.getElementById(rowId);
		if (commentsRow) {
			const hidden = commentsRow.hidden;
			commentsRow.hidden = !hidden;
			button.setAttribute('aria-pressed', !hidden ? 'true' : 'false');
		}
	}
}
