export async function apiQuery<Response>(
	path: string,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET',
	options: Omit<RequestInit, 'method'> = {}
): Promise<Response> {
	const { root, nonce } = window.attendanceData;
	const response = await fetch(root + path, {
		...options,
		headers: {
			...(options.headers ?? {}),
			'X-WP-Nonce': nonce,
		},
		method,
	});
	if (!response.ok) {
		throw new Error(`Got status ${response.status}`);
	}
	return response.json() as Response;
}

export function descendantListener<E extends Event>(
	selector: string,
	callback: (event: E) => void
): (event: E) => void {
	return (event) => {
		if (event.target instanceof Element) {
			const element = event.target.closest(selector);
			if (element) {
				callback({ ...event, target: element });
			}
		}
	};
}
