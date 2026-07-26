import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	// Server-rendered block: nothing is saved to post content.
	save: () => null,
} );
