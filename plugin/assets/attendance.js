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
 * Updates row with data
 * @param {string} orderId
 * @param {OrderRow} row
 */
function updateRow(orderId, row) {
	/** @type {HTMLTableRowElement|null} */
	const tr = document.getElementById(`order-${orderId}`);
	if (!tr) {
		return;
	}
	/** @type HTMLTableCellElement|null */
	const vax = tr.querySelector('.vaccinated');
	if (row.vaccinated && vax) {
		vax.textContent = '✔️'; // We never need the opposite as people don't get unvaxed
	}

	/** @type {HTMLButtonElement|null} */
	const checkedInBtn = tr.querySelector('.checked-in button');
	if (checkedInBtn) {
		if (row.checked_in) {
			checkedInBtn.textContent = 'Yes';
			checkedInBtn.classList.remove('button-primary');
		} else {
			checkedInBtn.textContent = 'No';
			checkedInBtn.classList.add('button-primary');
		}
	}
}

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
	try {
		const id = button.closest('tr')?.dataset.orderId;
		if (!id) {
			throw new Error('Could not find ID for button', button);
		}
		const result = await queryApi(id, true);
		updateRow(id, result);
	} catch (err) {
		console.warn(err);
	}
	button.disabled = false;
	button.classList.remove('disabled');
}

/**
 * @param {number|string} orderId
 * @param {bool} update
 * @returns {Promise<OrderRow>}
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
