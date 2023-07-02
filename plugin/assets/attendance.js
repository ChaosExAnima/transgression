/**
 * @param {MouseEvent} event
 */
async function toggleCheckIn(event) {
	const button = event.target;
	if (!(button instanceof HTMLButtonElement)) {
		return;
	}
	button.disabled = true;
	button.classList.add('disabled');
	/** @type HTMLTableCellElement */
	const td = button.parentElement;
	const id = td.parentElement.dataset.orderId;
	try {
		const result = await queryApi(id, true);
		if (result.checked_in) {
			button.textContent = 'Yes';
			button.classList.remove('button-primary');
		} else {
			button.textContent = 'No';
			button.classList.add('button-primary');
		}
		/** @type HTMLTableCellElement|null */
		const vax = td.parentElement.querySelector('.vaccinated');
		if (result.vaccinated && vax) {
			vax.textContent = '✔️';
		}
	} catch (err) {
		console.warn(err);
	}
	button.disabled = false;
	button.classList.remove('disabled');
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

/**
 * @param {number|string} orderId
 * @param {bool} update
 */
async function queryApi(orderId, update = false) {
	const { root, nonce } = window.attendanceData;
	const response = await fetch(`${root}/checkin/${orderId}`, {
		headers: {
			'X-WP-Nonce': nonce,
		},
		method: update ? 'PUT' : 'GET',
	});
	if (!response.ok) {
		throw new Error(`Got status ${response.status}`);
	}
	return response.json();
}
