import { __ } from '@wordpress/i18n';
import { Button, NoticeList, Panel, PanelBody, PanelRow, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

const SettingsPage = () => {
	const [clientId, setClientId] = useState('');
	const [clientSecret, setClientSecret] = useState('');
	const { createSuccessNotice } = useDispatch(noticesStore);
	const { removeNotice } = useDispatch(noticesStore);
	const notices = useSelect((select) => select(noticesStore).getNotices());

	useEffect(() => {
		apiFetch({ path: '/wp/v2/settings' }).then((settings) => {
			setClientId(settings.sesamy_settings.client_id);
		});
	}, []);

	const saveSettings = () => {
		apiFetch({
			path: '/wp/v2/settings',
			method: 'POST',
			data: {
				sesamy_settings: {
					client_id: clientId,
				},
			},
		}).then(() => {
			createSuccessNotice(__('Settings saved.', 'sesamy'));
		});
	};

	return (
		<>
			{notices.length > 0 && <NoticeList notices={notices} onRemove={removeNotice} />}

			<h1>{__('Sesamy Settings', 'sesamy')}</h1>

			<Panel>
				<PanelBody>
					<PanelRow>
						<TextControl
							label={__('Client-ID', 'sesamy')}
							value={clientId}
							onChange={setClientId}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__('Client-Secret', 'sesamy')}
							value={clientSecret}
							onChange={setClientSecret}
							help={__(
								'Get your Client-ID and Client-Secret from the Sesamy dashboard.',
								'sesamy',
							)}
						/>
					</PanelRow>
				</PanelBody>
			</Panel>

			<Button variant="primary" onClick={saveSettings}>
				{__('Save', 'sesamy')}
			</Button>
		</>
	);
};

export default SettingsPage;
