import metadata from './block.json';

declare global {
	interface Window {
		wp: {
			blocks: typeof import('@wordpress/blocks');
			serverSideRender: typeof import('@wordpress/server-side-render');
			blockEditor: typeof import('@wordpress/block-editor');
		};
	}
}

const { React, wp } = window;
const {
	blocks: { registerBlockType },
	blockEditor: { useBlockProps },
	serverSideRender: ServerSideRender,
} = wp;

registerBlockType('transgression/tickets', {
	...metadata,
	edit: () => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<ServerSideRender block={metadata.name} />
			</div>
		);
	},
	attributes: {},
	icon: 'tickets-alt',
});
