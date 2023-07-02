declare global {
	interface Window {
		attendanceData: {
			root: string;
			nonce: string;
		};
	}
}

interface OrderRow {
	id: string;
	pic: string;
	name: string;
	email: string;
	user_id: number;
	volunteer: boolean;
	checked_in: boolean;
	vaccinated: boolean;
}

function main() {
	const body = document.getElementById('attendance');
	if (!body) {
		return;
	}
	body.querySelectorAll<HTMLButtonElement>('.checked-in > button').forEach(
		(ele) => ele.addEventListener('click', toggleCheckIn)
	);
}

document.addEventListener('DOMContentLoaded', main);

function updateRow(orderId: string, row: OrderRow) {
	const tr = document.getElementById(`order-${orderId}`);
	if (!(tr instanceof HTMLTableRowElement)) {
		return;
	}
	const vax = tr.querySelector('.vaccinated');
	if (row.vaccinated && vax instanceof HTMLTableCellElement) {
		vax.textContent = '✔️'; // We never need the opposite as people don't get unvaxed
	}

	const checkedInBtn = tr.querySelector('.checked-in button');
	if (checkedInBtn instanceof HTMLButtonElement) {
		if (row.checked_in) {
			checkedInBtn.textContent = 'Yes';
			checkedInBtn.classList.remove('button-primary');
		} else {
			checkedInBtn.textContent = 'No';
			checkedInBtn.classList.add('button-primary');
		}
	}
}

async function toggleCheckIn(event: MouseEvent) {
	const button = event.target;
	if (!(button instanceof HTMLButtonElement)) {
		return;
	}
	button.disabled = true;
	button.classList.add('disabled');
	try {
		const id = button.closest('tr')?.dataset.orderId;
		if (!id) {
			throw new Error('Could not find ID for button');
		}
		const result = await queryApi(id, true);
		updateRow(id, result);
	} catch (err) {
		console.warn(err);
	}
	button.disabled = false;
	button.classList.remove('disabled');
}

async function queryApi(orderId: string, update = false): Promise<OrderRow> {
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

export {};
