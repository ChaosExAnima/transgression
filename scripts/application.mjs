import { faker } from '@faker-js/faker';
import { JSDOM } from 'jsdom';

const pagePath = process.argv[2] ?? 'apply';

const pageData = await fetch(`http://localhost:8888/${pagePath}/`);
if (!pageData.ok) {
	throw new Error(`Got status ${pageData.status}`);
}
const pageHtml = await pageData.text();

const pageDom = new JSDOM(pageHtml);

const form = pageDom.window.document.querySelector('form');
if (!form) {
	throw new Error('Could not find the form!');
}

const formdata = new pageDom.window.FormData(form);

const name = faker.name.firstName();
formdata.set('name', name);

const pronouns = ['she/her', 'he/him', 'they/them', 'xhe/xer', 'ze/zir', 'ze/hir'];
formdata.set('pronouns', faker.helpers.arrayElement(pronouns));

formdata.set('email', faker.internet.email(name, undefined, 'test.com').toLowerCase());

formdata.set('identify', faker.name.gender());

const imageUrl = faker.image.people(800, 800);
formdata.set('photo-url', imageUrl);

const imageData = await fetch(imageUrl);
if (imageData.status !== 200) {
	console.warn(`Could not fetch image ${imageUrl}, got status ${imageData.status}`);
} else {
	const imageBlob = await imageData.blob();
	console.log(imageBlob.type);
	formdata.set('photo-image', imageBlob);
}

formdata.set('accessibility', faker.lorem.sentence());
formdata.set('are_you_going_to_be_there_with_any', faker.name.firstName());
formdata.set('have_you_fully_read_and_agree_to_a', 'yes');

console.log();
console.log('Here is the final submission:');
for (const [key, field] of formdata) {
	if (typeof field === 'string' && key.charAt(0) !== '_') {
		console.log(capitalize(key).replace(/[-_]/g, ' ').trim() + ': ' + field);
	}
}
// process.exit();

console.log();
const response = await fetch(form.action, {
	method: form.method,
	body: formdata,
});
if (!response.ok) {
	console.error(`Error: got status ${response.status} when submitting the form`);
	process.exit(1);
}

console.log('Application submitted successfully!');
process.exit();

/**
 * @param {string} input
 * @returns string
 */
function capitalize(input) {
	return input.charAt(0).toUpperCase() + input.slice(1);
}
