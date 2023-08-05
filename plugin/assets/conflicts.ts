declare global {
	interface Window {
		conflictsData: {
			flags: {
				[name: string]: {
					id: number;
					source_id?: number;
				};
			};
		};
	}
}

const actions = document.querySelectorAll<HTMLElement>('.actions > *');
for (const button of actions) {
	button.addEventListener('click', handleAction);
}

const textCells = document.querySelectorAll<HTMLTableCellElement>('.text');
const flagNames = Object.keys(window.conflictsData.flags);
for (const cell of textCells) {
	if (cell.textContent) {
		for (const name of flagNames) {
			if (cell.textContent.includes(name)) {
				console.log(`Found ${name} in cell: ${cell.textContent}`);
			}
		}
	}
}

function handleAction(event: MouseEvent) {
	const button = event.currentTarget;
	if (
		!(button instanceof HTMLButtonElement) &&
		!(button instanceof HTMLAnchorElement)
	) {
		return;
	}
	switch (button.dataset.action) {
		case 'comment':
			event.preventDefault();
			return commentAction(button);
		case 'flag':
			event.preventDefault();
			return flagAction(button);
		case 'resolve':
			return resolveAction();
	}
}

function resolveAction() {
	return confirm('Resolve this conflict?');
}

function flagAction(button: HTMLElement) {
	const selection = window.getSelection();
	if (!selection || !selection.rangeCount) {
		alert('Please select the name to add');
		return;
	}
	const row = selection.anchorNode;
	if (!button.closest('tr')?.contains(row)) {
		return;
	}
	const name = selection.toString().trim();
	if (!name.length || !confirm(`Flag ${name}?`)) {
		return;
	}
	location.assign(`${button.dataset.url}&flag=${name}`);
}

function commentAction(button: HTMLElement) {
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

export {};
