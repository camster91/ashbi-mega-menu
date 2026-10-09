/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import { megamenuIcon } from './icon';

registerBlockType(metadata.name, {
	icon: megamenuIcon,
	edit: Edit,
	save: () => null, // Dynamic block — rendered server-side.
});
