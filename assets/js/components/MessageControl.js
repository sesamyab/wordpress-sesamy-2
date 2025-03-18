import { __ } from '@wordpress/i18n';
import { TextareaControl } from '@wordpress/components';

const MessageControl = ({ value, onChange }) => {
	return (
		<TextareaControl
			label={__('Message', 'sesamy')}
			value={value}
			onChange={onChange}
			__nextHasNoMarginBottom
		/>
	);
};

export default MessageControl;
