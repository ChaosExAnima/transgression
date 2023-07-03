export async function apiQuery<Response>(
	path: string,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET'
): Promise<Response> {
	const { root, nonce } = window.attendanceData;
	const response = await fetch(root + path, {
		headers: {
			'X-WP-Nonce': nonce,
		},
		method,
	});
	if (!response.ok) {
		throw new Error(`Got status ${response.status}`);
	}
	return response.json() as Response;
}
