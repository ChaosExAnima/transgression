const { React, wp } = window;
const {
	blockEditor: { useBlockProps },
} = wp;

export default function ApplyFormEdit() {
	const blockProps = useBlockProps();
	return <form {...blockProps}></form>;
}
