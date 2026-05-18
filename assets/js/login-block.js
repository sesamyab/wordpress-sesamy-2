import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

// core/navigation declares its allowedBlocks in block.json, which is baked
// into the editor's JS bundle. Extending it server-side has no effect on
// the editor — we have to filter the block settings client-side. The editor
// calls reapplyBlockTypeFilters() before registerCoreBlocks(), so this
// applies regardless of script load order.
addFilter('blocks.registerBlockType', 'sesamy/login/extend-navigation', (settings, name) => {
	if (name !== 'core/navigation') {
		return settings;
	}
	return {
		...settings,
		allowedBlocks: [...(settings.allowedBlocks ?? []), 'sesamy/login'],
	};
});

const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps({ className: 'sesamy-login-block' });
	const { buttonText } = attributes;
	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Login button', 'sesamy')}>
					<TextControl
						__nextHasNoMarginBottom
						label={__('Button label', 'sesamy')}
						value={buttonText || ''}
						onChange={(value) => setAttributes({ buttonText: value })}
						help={__('Leave empty to use the default label.', 'sesamy')}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<span className="sesamy-login-block__placeholder">
					{buttonText ? `[sesamy-login "${buttonText}"]` : '[sesamy-login]'}
				</span>
			</div>
		</>
	);
};

registerBlockType('sesamy/login', {
	apiVersion: 3,
	title: __('Sesamy Login', 'sesamy'),
	description: __(
		'Adds the Sesamy login button. Place it inside a Navigation block or anywhere else on the page.',
		'sesamy',
	),
	category: 'widgets',
	icon: 'lock',
	attributes: {
		buttonText: {
			type: 'string',
			role: 'content',
		},
	},
	supports: {
		html: false,
		className: true,
		// Marks this as a "content block" so the editor allows inserting it
		// into Navigation when the menu is locked to contentOnly editing.
		contentRole: true,
	},
	edit: Edit,
	save: () => null,
});
