const { React, wp } = window;
const {
	blockEditor: { useBlockProps },
} = wp;

export default function ApplyFormSave() {
	return <form {...useBlockProps.save()}>

	</form>;
}
