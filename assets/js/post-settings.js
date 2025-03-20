/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { ToggleControl, PanelRow, TextControl } from '@wordpress/components';

import { PluginDocumentSettingPanel as PluginDocumentSettingPanelDeprecated } from '@wordpress/edit-post'; // Deprecated from WP 6.6
import { PluginDocumentSettingPanel as PluginDocumentSettingPanelCurrent } from '@wordpress/editor'; // Only available from WP 6.6
import { useState } from '@wordpress/element';

const PluginDocumentSettingPanel =
	PluginDocumentSettingPanelCurrent ?? PluginDocumentSettingPanelDeprecated;

const SesamySettingsPanel = () => {
	const settings = useSelect((select) => {
		return select('core').getEntityRecord('root', 'site')?.sesamy_settings;
	}, []);
	const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta'));
	const { editPost } = useDispatch('core/editor');

	const {
		_sesamy_locked,
		_sesamy_access_level,
		_sesamy_enable_single_purchase,
		_sesamy_price,
		_sesamy_custom_paywall_url,
	} = meta;

	const [displayPrice, setDisplayPrice] = useState(_sesamy_price ? _sesamy_price.toString() : '');

	const handlePriceChange = (value) => {
		// Validate the input value
		// Allow empty input, positive numbers, and at most one decimal separator (. or ,)
		if (value === '' || /^\d*([.,]\d*)?$/.test(value)) {
			// Store the string value in the UI
			setDisplayPrice(value);

			// Convert to numeric value for storage
			let numericValue = null;
			if (value !== '') {
				// Replace comma with dot for numeric conversion
				const normalizedValue = value.replace(',', '.');
				numericValue = parseFloat(normalizedValue);
				// Round to 2 decimal places if needed
				numericValue = Math.round(numericValue * 100) / 100;
			}

			// Store the numeric value in the post meta
			editPost({
				meta: {
					...meta,
					_sesamy_price: numericValue,
				},
			});
		}

		return _sesamy_price === null ? '' : _sesamy_price.toString();
	};

	return (
		<PluginDocumentSettingPanel
			name="sesamy-settings-panel"
			title={__('Sesamy', 'sesamy')}
			className="sesamy-settings-panel"
		>
			<PanelRow>
				<ToggleControl
					__nextHasNoMarginBottom
					label={__('Lock this article', 'sesamy')}
					checked={_sesamy_locked}
					onChange={(value) => {
						editPost({ meta: { ...meta, _sesamy_locked: value } });
					}}
				/>
			</PanelRow>
			{_sesamy_locked && (
				<>
					{/* TODO: access level
					<PanelRow>
						<SelectControl
							__nextHasNoMarginBottom
							label={__('Lock mode', 'sesamy')}
							value={_sesamy_access_level}
							options={[
								{
									label: __('Unlock for users with pass/entitlement', 'sesamy'),
									value: 'entitlement',
								},
								{
									label: __('Unlock for logged in users', 'sesamy'),
									value: 'logged-in',
								},
							]}
							onChange={(value) => {
								editPost({ meta: { ...meta, _sesamy_access_level: value } });
							}}
						/>
					</PanelRow>
					*/}
					{_sesamy_access_level === 'entitlement' && (
						<>
							<PanelRow>
								<ToggleControl
									__nextHasNoMarginBottom
									checked={_sesamy_enable_single_purchase}
									label={__('Enable single-purchase', 'sesamy')}
									onChange={(value) => {
										editPost({
											meta: {
												...meta,
												_sesamy_enable_single_purchase: value,
											},
										});
									}}
									style={{ marginTop: '30px' }}
								/>
							</PanelRow>
							{_sesamy_enable_single_purchase && (
								<PanelRow>
									<TextControl
										__nextHasNoMarginBottom
										label={`${__('Price', 'sesamy')}${settings?.default_currency ? ` (in ${settings.default_currency})` : ''}`}
										value={displayPrice}
										onChange={handlePriceChange}
										help={
											settings?.default_price && settings?.default_currency
												? `Optional, default article price is ${settings?.default_price} ${settings?.default_currency}`
												: ''
										}
									/>
								</PanelRow>
							)}
						</>
					)}
					<PanelRow>
						<TextControl
							__nextHasNoMarginBottom
							label={__('Custom Paywall URL', 'sesamy')}
							value={_sesamy_custom_paywall_url}
							onChange={(value) => {
								editPost({
									meta: { ...meta, _sesamy_custom_paywall_url: value },
								});
							}}
							help={__(
								'Optional, default Paywall URL from your config will be used if empty.',
								'sesamy',
							)}
						/>
					</PanelRow>
				</>
			)}
		</PluginDocumentSettingPanel>
	);
};

registerPlugin('sesamy-post-editor', {
	render: SesamySettingsPanel,
	icon: '',
});
