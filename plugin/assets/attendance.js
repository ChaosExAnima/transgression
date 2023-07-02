/**
 * @param {MouseEvent} event
 */
function toggleCheckIn(event) {
	const button = event.target;
	if (!(button instanceof HTMLButtonElement)) {
		return;
	}
	/** @type HTMLTableCellElement */
	const td = button.parentElement;
	const id = td.parentElement.dataset.orderId;
	console.log(`ID: ${id}`);
	if (button.textContent === 'No') {
		button.textContent = 'Yes';
	} else {
		button.textContent = 'No';
	}
}

function main() {
	const body = document.getElementById('attendance');
	if (!body) {
		return;
	}
	body.querySelectorAll('.checked-in > button').forEach((ele) =>
		ele.addEventListener('click', toggleCheckIn)
	);
}

document.addEventListener('DOMContentLoaded', main);
