import { apiQuery } from './lib';

declare global {
	interface Window {
		attendanceData: {
			root: string;
			nonce: string;
			orders: OrderRow[];
		};
	}
}

interface OrderRow {
	id: number;
	pic: string;
	name: string;
	email: string;
	user_id: number;
	volunteer: boolean;
	checked_in: boolean;
	vaccinated: boolean;
}

type ColFields = 'id' | 'name' | 'email';

const table = document.getElementById('attendance') as HTMLTableElement;
const percentage = document.getElementById('percentage') as HTMLMeterElement;
const searchInput = document.getElementById('search') as HTMLInputElement;
const productSelect = document.getElementById('product') as HTMLSelectElement;

let orders = window.attendanceData.orders;

function main() {
	if (!table) {
		return;
	}
	table
		.querySelectorAll<HTMLButtonElement>('.checked-in > button')
		.forEach((ele) => ele.addEventListener('click', toggleCheckIn));

	searchInput.addEventListener('input', updateRows);
	if (getSearch()) {
		updateRows();
	}

	const observer = new IntersectionObserver(
		([e]) => e.target.classList.toggle('stuck', e.intersectionRatio < 1),
		{ threshold: [1] }
	);
	if (searchInput.parentElement) {
		observer.observe(searchInput.parentElement);
	}
	const checkedInCount = orders.filter((order) => order.checked_in).length;
	percentage.value = checkedInCount;
	percentage.max = orders.length;
	percentage.textContent = `${checkedInCount} / ${orders.length}`;
	percentage.title = percentage.textContent;

	setInterval(updateOrders, 5000);
}

document.addEventListener('DOMContentLoaded', main);

function getSearch() {
	const search = searchInput.value.trim();
	if (search.length > 1) {
		return search.toLowerCase();
	}
	return null;
}

function updateRows() {
	const search = getSearch();
	for (const order of orders) {
		updateRowSearch(order, search);
	}
}

function updateRowSearch(order: OrderRow, search: string | null) {
	const tr = document.getElementById(`order-${order.id}`);
	if (!(tr instanceof HTMLTableRowElement)) {
		return;
	}

	const fields = tr.querySelectorAll<HTMLTableCellElement>('td[data-col]');
	let found = false;
	for (const field of fields) {
		const col = field.dataset.col;
		if (!col) {
			continue;
		}
		// Get raw value from source, ideally
		let plainValue = field.textContent ?? '';
		if (col === 'id') {
			plainValue = `#${order.id}`;
		} else if (col in order) {
			plainValue = order[col as ColFields].toString();
		}

		const loc = plainValue.toLowerCase().indexOf(search ?? '');
		if (search && -1 !== loc) {
			found = true;
			field.innerHTML = plainValue.replaceAll(
				new RegExp(search, 'ig'),
				() =>
					`<mark>${plainValue.slice(loc, loc + search.length)}</mark>`
			);
		} else {
			field.innerHTML = plainValue;
		}
	}

	if (search && !found) {
		tr.classList.add('hidden');
	} else {
		tr.classList.remove('hidden');
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
		const result = await apiCheckin(id, true);
		updateRowData(result);
		if (!result.checked_in) {
			button.disabled = false;
			button.classList.remove('disabled');
		}
	} catch (err) {
		console.warn(err);
		button.disabled = false;
		button.classList.remove('disabled');
	}
}

function updateRowData(order: OrderRow) {
	const tr = document.getElementById(`order-${order.id}`);
	if (!(tr instanceof HTMLTableRowElement)) {
		return;
	}
	const vax = tr.querySelector<HTMLTableCellElement>('.vaccinated');
	if (order.vaccinated && vax) {
		vax.textContent = '✔️'; // We never need the opposite as people don't get unvaxed
	}

	const checkedInBtn =
		tr.querySelector<HTMLButtonElement>('.checked-in button');
	if (checkedInBtn) {
		const checkedIn = order.checked_in;
		checkedInBtn.textContent = checkedIn ? 'Yes' : 'No';
		checkedInBtn.classList.toggle('button-primary', !checkedIn);
		checkedInBtn.classList.toggle('disabled', checkedIn);
		checkedInBtn.disabled = checkedIn;
	}
}

async function updateOrders() {
	const productId = productSelect.value;
	if (document.visibilityState !== 'visible' || !productId) {
		return;
	}
	const results = await apiQuery<OrderRow[]>(`/orders/${productId}`);
	results.forEach(updateRowData);
	const checkedInCount = results.filter((order) => order.checked_in).length;
	percentage.value = checkedInCount;
	percentage.textContent = `${checkedInCount} / ${results.length}`;
	orders = results;
}

function apiCheckin(orderId: string, update = false): Promise<OrderRow> {
	return apiQuery(`/checkin/${orderId}`, update ? 'PUT' : 'GET');
}
