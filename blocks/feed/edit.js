import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { feedId, layout, count } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Feed settings', 'freshet-feeds' ) }>
					<FeedSelect
						value={ feedId }
						onChange={ ( value ) =>
							setAttributes( { feedId: parseInt( value, 10 ) || 0 } )
						}
					/>
					<SelectControl
						label={ __( 'Layout', 'freshet-feeds' ) }
						value={ layout }
						options={ [
							{ label: __( 'Feed default', 'freshet-feeds' ), value: '' },
							{ label: __( 'Grid', 'freshet-feeds' ), value: 'grid' },
							{ label: __( 'List', 'freshet-feeds' ), value: 'list' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
						help={ __(
							'Themes can add custom layouts via freshet-feeds/layout-{name}.php.',
							'freshet-feeds'
						) }
					/>
					<RangeControl
						label={ __( 'Items (0 = feed default)', 'freshet-feeds' ) }
						value={ count }
						min={ 0 }
						max={ 50 }
						onChange={ ( value ) => setAttributes( { count: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ feedId === 0 ? (
				<Placeholder
					icon="rss"
					label={ __( 'Freshet Feeds', 'freshet-feeds' ) }
					instructions={ __(
						'Select a feed in the block settings sidebar. Feeds are managed under the “Feeds” admin menu.',
						'freshet-feeds'
					) }
				/>
			) : (
				<ServerSideRender
					block="freshet-feeds/feed"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => <Spinner /> }
				/>
			) }
		</div>
	);
}

function FeedSelect( { value, onChange } ) {
	const [ feeds, setFeeds ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/freshet-feeds/v1/feeds' } )
			.then( setFeeds )
			.catch( () => setFeeds( [] ) );
	}, [] );

	if ( feeds === null ) {
		return <Spinner />;
	}

	return (
		<SelectControl
			label={ __( 'Feed', 'freshet-feeds' ) }
			value={ String( value ) }
			options={ [
				{ label: __( '— select a feed —', 'freshet-feeds' ), value: '0' },
				...feeds.map( ( feed ) => ( {
					label: `${ feed.name } (${ feed.slug })`,
					value: String( feed.id ),
				} ) ),
			] }
			onChange={ onChange }
		/>
	);
}
