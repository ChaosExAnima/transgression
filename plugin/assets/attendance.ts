import { apiQuery, descendantListener } from './lib';

declare global {
	interface Window {
		attendanceData: {
			root: string;
			nonce: string;
			orders: Omit<OrderRow, 'element'>[];
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
	element?: HTMLTableRowElement | null;
}

type ColFields = 'id' | 'name' | 'email';

class Attendance {
	protected table: HTMLTableElement;
	protected percentage: HTMLMeterElement;
	protected searchInput: HTMLInputElement;
	protected productSelect: HTMLSelectElement;

	protected orderMap: Map<number, OrderRow>;
	protected observer = new IntersectionObserver(
		([e]) => e.target.classList.toggle('stuck', e.intersectionRatio < 1),
		{ threshold: [1] }
	);

	public constructor() {
		this.table = document.getElementById('attendance') as HTMLTableElement;
		this.percentage = document.getElementById(
			'percentage'
		) as HTMLMeterElement;
		this.searchInput = document.getElementById(
			'search'
		) as HTMLInputElement;
		this.productSelect = document.getElementById(
			'product'
		) as HTMLSelectElement;

		this.orderMap = new Map(
			window.attendanceData.orders.map((order) => [
				order.id,
				{ ...order, element: this.getRowByOrder(order) },
			])
		);

		if (!this.table) {
			return;
		}

		this.table.addEventListener(
			'click',
			descendantListener(
				'.checked-in > button',
				this.toggleCheckIn.bind(this)
			)
		);

		if (this.searchInput.parentElement) {
			this.observer.observe(this.searchInput.parentElement);
		}

		this.updatePercentage();
		setInterval(this.pollOrderApi.bind(this), 5000);
	}

	protected updateOrders(orders: OrderRow[]) {
		for (const order of orders) {
			const oldOrder = this.orderMap.get(order.id);
			if (!oldOrder) {
				continue;
			}
			if (oldOrder.checked_in !== order.checked_in) {
				this.updateRowDisplay(order);
			}
		}
		this.orderMap = new Map(
			orders.map((order) => [
				order.id,
				{ ...order, element: this.getRowByOrder(order) },
			])
		);
		this.updatePercentage();
	}

	protected getRowByOrder(order: OrderRow): HTMLTableRowElement | null {
		if (order.element instanceof HTMLTableRowElement) {
			return order.element;
		}
		return document.getElementById(
			`order-${order.id}`
		) as HTMLTableRowElement | null;
	}

	protected updateRowDisplay(order: OrderRow) {
		const tr = this.getRowByOrder(order);
		if (!tr) {
			return;
		}
		const vax = tr.querySelector<HTMLTableCellElement>('.vaccinated');
		if (order.vaccinated && vax && !vax.textContent?.length) {
			vax.textContent = '✔️'; // We never need the opposite as people don't get unvaxed
		}

		const checkedIn = order.checked_in;
		const checkedInBtn =
			tr.querySelector<HTMLButtonElement>('.checked-in button');
		if (
			checkedInBtn &&
			checkedIn !== !checkedInBtn.classList.contains('button-primary')
		) {
			checkedInBtn.textContent = checkedIn ? 'Yes' : 'No';
			checkedInBtn.classList.toggle('button-primary', !checkedIn);
		}
	}

	protected updatePercentage() {
		const orders = Array.from(this.orderMap.values());
		const checkedInCount = orders.filter(
			(order) => order.checked_in
		).length;
		if (this.percentage.max <= 1) {
			this.percentage.max = orders.length;
		}
		this.percentage.value = checkedInCount;
		this.percentage.textContent = `${checkedInCount} / ${orders.length}`;
		this.percentage.title = this.percentage.textContent;
	}

	// Check in

	protected async toggleCheckIn(event: MouseEvent) {
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
			const result = await apiQuery<OrderRow>(`/checkin/${id}`, 'PUT');
			this.updateRowDisplay(result);
		} catch (err) {
			console.warn(err);
		}
		button.disabled = false;
		button.classList.remove('disabled');
	}

	protected async pollOrderApi() {
		const productId = this.productSelect.value;
		if (document.visibilityState !== 'visible' || !productId) {
			return;
		}
		const results = await apiQuery<OrderRow[]>(`/orders/${productId}`);
		this.updateOrders(results);
	}

	// Search

	protected getSearch() {
		const search = this.searchInput.value.trim();
		if (search.length > 1) {
			return search.toLowerCase();
		}
		return null;
	}

	protected doSearch() {
		const search = this.getSearch();
		for (const order of this.orderMap.values()) {
			this.updateRowSearch(order, search);
		}
	}

	protected updateRowSearch(order: OrderRow, search: string | null) {
		const tr = this.getRowByOrder(order);
		if (!tr) {
			return;
		}

		const fields =
			tr.querySelectorAll<HTMLTableCellElement>('td[data-col]');
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
			const textEle = field.children.item(0) ?? field;
			if (search && -1 !== loc) {
				found = true;
				textEle.innerHTML = plainValue.replaceAll(
					new RegExp(search, 'ig'),
					() =>
						`<mark>${plainValue.slice(
							loc,
							loc + search.length
						)}</mark>`
				);
			} else {
				textEle.innerHTML = plainValue;
			}
		}

		if (search && !found) {
			tr.classList.add('hidden');
		} else {
			tr.classList.remove('hidden');
		}
	}
}

document.addEventListener('DOMContentLoaded', () => new Attendance());
