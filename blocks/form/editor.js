/**
 * Review Captain – editor UI for review-captain/form (no build step).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var Notice = wp.components.Notice;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'review-captain/form', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Context', 'review-captain' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'What is the review about?', 'review-captain' ),
							value: a.contextType,
							options: [
								{ label: __( 'Company in general', 'review-captain' ), value: '' },
								{ label: __( 'Post/page', 'review-captain' ), value: 'post' },
								{ label: __( 'Product', 'review-captain' ), value: 'product' },
								{ label: __( 'Event', 'review-captain' ), value: 'event' },
								{ label: __( 'Course', 'review-captain' ), value: 'course' },
								{ label: __( 'Voucher', 'review-captain' ), value: 'voucher' },
								{ label: __( 'App', 'review-captain' ), value: 'app' }
							],
							onChange: function ( v ) { set( { contextType: v } ); }
						} ),
						a.contextType && el( TextControl, {
							label: __( 'Context ID', 'review-captain' ),
							help: __( '“current” uses the current post.', 'review-captain' ),
							value: a.contextId,
							onChange: function ( v ) { set( { contextId: v } ); }
						} ),
						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__( 'Depending on your settings, new reviews are published only after moderation. Set the consent and privacy text under Settings.', 'review-captain' )
						)
					)
				),
				el( ServerSideRender, { block: 'review-captain/form', attributes: a } )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
