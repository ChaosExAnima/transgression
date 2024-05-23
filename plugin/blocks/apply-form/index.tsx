import metadata from './block.json';

import '../global';

import ApplyFormEdit from './edit';
import ApplyFormSave from './save';

const { wp } = window;
const {
	blocks: { registerBlockType },
} = wp;

export interface ApplyFormAttributes {
	nameText: string;
	nameHelper: string;
	pronounsText: string;
	pronounsHelper: string;
	emailText: string;
	emailHelper: string;
	identityText: string;
	identityHelper: string;
	photoText: string;
	photoHelper: string;
	socialsText: string;
	socialsHelper: string;
	accessibilityText: string;
	accessibilityHelper: string;
	refererText: string;
	refererHelper: string;
	friendsText: string;
	friendsHelper: string;
	conflictsText: string;
	conflictsHelper: string;

}

registerBlockType('transgression/apply-form', {
	...metadata,
	edit: ApplyFormEdit,
	save: ApplyFormSave,
	attributes: {},
	icon: 'text',
});
