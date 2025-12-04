import { ValidatedTextInput } from '@woocommerce/blocks-checkout';
import { useBlockProps } from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';

export const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	return (
		<div {...blockProps}>
			<div className={'example-fields'}>Dit is het Sendy block.</div>
		</div>
	);
};
