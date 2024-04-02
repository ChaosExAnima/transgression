import metadata from './block.json';

import '../global';

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
