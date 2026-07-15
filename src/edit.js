import { useBlockProps } from '@wordpress/block-editor';

export const Edit = () => {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<h3 className="wc-block-components-title">Pick-up punt</h3>

			<button onClick={ ( e ) => e.preventDefault() }>
				Selecteer pick-up punt
			</button>

			<p>
				<small>
					Dit blok is alleen zichtbaar bij verzending naar een pick-up
					punt
				</small>
			</p>
		</div>
	);
};
