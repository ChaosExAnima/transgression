const actions = document.querySelectorAll<HTMLElement>('.actions > *');
for (const button of actions) {
	button.addEventListener('click', handleAction);
}

function handleAction(event: MouseEvent) {
	const target = event.currentTarget;
	if (!(target instanceof HTMLElement)) {
		return;
	}
	switch (target.dataset.action) {
		case 'comment':
		case 'flag':
			event.preventDefault();
			return toggleRow(target);
		case 'resolve':
			return confirm('Resolve this conflict?');
	}
}

function toggleRow(button: HTMLElement) {
	const rowId = button.getAttribute('aria-controls');
	if (!rowId) {
		return;
	}
	const detailsRow = document.getElementById(rowId);
	if (detailsRow) {
		const hidden = detailsRow.hidden;
		detailsRow.hidden = !hidden;
		button.setAttribute('aria-pressed', hidden ? 'true' : 'false');
	}
}

export {};
