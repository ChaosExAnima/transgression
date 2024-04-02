import metadata from './block.json';

import '../global';

import ApplyFormEdit from './edit';
import ApplyFormSave from './save';

const { wp } = window;
const {
	blocks: { registerBlockType },
} = wp;

registerBlockType('transgression/apply-form', {
	...metadata,
	edit: ApplyFormEdit,
	save: ApplyFormSave,
	attributes: {},
	icon: 'text',
});
