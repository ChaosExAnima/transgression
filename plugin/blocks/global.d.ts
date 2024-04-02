declare global {
	interface Window {
		wp: {
			blocks: typeof import('@wordpress/blocks');
			serverSideRender: typeof import('@wordpress/server-side-render');
			blockEditor: typeof import('@wordpress/block-editor');
		};
	}
}

export {}
