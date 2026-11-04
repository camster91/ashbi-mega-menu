/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Placeholder,
	Spinner,
	Button,
	Notice,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { megamenuIcon } from './icon';

/**
 * Internal dependencies
 */
import './editor.css';

export default function Edit({ attributes, setAttributes }) {
	const { menuId, context } = attributes;
	const blockProps = useBlockProps({
		className: 'wp-block-ashbi-mega-menu-mega-menu',
	});

	const blockData = window.abmmBlockData;
	const menus = blockData?.menus || {};
	const isReady = blockData?.status === 'ready';
	const hasMenus = Object.keys(menus).length > 0;
	const selectedMenuMissing = Boolean(menuId && isReady && !menus[menuId]);

	// Menus can share a name (for example several copies of the sample), so
	// add the menu ID to repeated names to tell them apart.
	const titleCounts = Object.values(menus).reduce((counts, menu) => {
		const title = menu.title || '';
		counts[title] = (counts[title] || 0) + 1;
		return counts;
	}, {});
	const menuOptions = [
		{ value: '', label: __('— Select a menu —', 'ashbi-mega-menu') },
		...Object.entries(menus).map(([id, menu]) => ({
			value: id,
			label: !menu.title ? id : titleCounts[menu.title] > 1 ? `${menu.title} (${id})` : menu.title,
		})),
	];

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Menu Settings', 'ashbi-mega-menu')}>
					{hasMenus && <SelectControl
						label={__('Select Menu', 'ashbi-mega-menu')}
						value={menuId}
						options={menuOptions}
						onChange={(value) => setAttributes({ menuId: value })}
					/>}
					{blockData?.contextualNavigation && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={__('Use page product navigation', 'ashbi-mega-menu')}
							help={__("On a product's pages, show that product's links under the menu.", 'ashbi-mega-menu')}
							checked={context === 'page'}
							onChange={(on) => setAttributes({ context: on ? 'page' : '' })}
						/>
					)}
				</PanelBody>
			</InspectorControls>

			{!isReady && (
				<Placeholder icon={megamenuIcon} label={__('Ashbi Mega Menu', 'ashbi-mega-menu')}>
					<Spinner />
					<p>{__('Loading available menus…', 'ashbi-mega-menu')}</p>
				</Placeholder>
			)}

			{isReady && !hasMenus && (
				<Placeholder
					icon={megamenuIcon}
					label={__('Ashbi Mega Menu', 'ashbi-mega-menu')}
					instructions={
						blockData?.canManage
							? __('Create a menu first, then return to select it here.', 'ashbi-mega-menu')
							: __('No menus are available. Ask a site administrator to create one.', 'ashbi-mega-menu')
					}
				>
					{blockData?.canManage && (
						<Button variant="primary" href={blockData.manageUrl}>
							{__('Create or manage menus', 'ashbi-mega-menu')}
						</Button>
					)}
				</Placeholder>
			)}

			{isReady && hasMenus && !menuId && (
				<Placeholder
					icon={megamenuIcon}
					label={__('Ashbi Mega Menu', 'ashbi-mega-menu')}
					instructions={__('Select a mega menu to display.', 'ashbi-mega-menu')}
				>
					<SelectControl
						value={menuId}
						options={menuOptions}
						onChange={(value) => setAttributes({ menuId: value })}
					/>
				</Placeholder>
			)}

			{selectedMenuMissing && (
				<Notice status="warning" isDismissible={false}>
					<p>{__('The selected menu no longer exists. Choose a replacement.', 'ashbi-mega-menu')}</p>
					<SelectControl
						value=""
						options={menuOptions}
						onChange={(value) => setAttributes({ menuId: value })}
					/>
				</Notice>
			)}

			{menuId && !selectedMenuMissing && (
				<ServerSideRender
					block="ashbi-mega-menu/mega-menu"
					attributes={attributes}
					LoadingResponsePlaceholder={() => (
						<div className="abmm-block-preview-placeholder">
							<Spinner />
							<p>{__('Loading preview…', 'ashbi-mega-menu')}</p>
						</div>
					)}
					ErrorResponsePlaceholder={({ response }) => (
						<div className="abmm-block-preview-placeholder">
							<p>
								{__('Preview error: ', 'ashbi-mega-menu')}
								{response?.errorMsg || __('Unknown error', 'ashbi-mega-menu')}
							</p>
						</div>
					)}
				/>
			)}
		</div>
	);
}
