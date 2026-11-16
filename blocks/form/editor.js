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
						{ title: __( 'Kontext', 'review-captain' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Worauf bezieht sich die Bewertung?', 'review-captain' ),
							value: a.contextType,
							options: [
								{ label: __( 'Unternehmen allgemein', 'review-captain' ), value: '' },
								{ label: __( 'Beitrag/Seite', 'review-captain' ), value: 'post' },
								{ label: __( 'Produkt', 'review-captain' ), value: 'product' },
								{ label: __( 'Event', 'review-captain' ), value: 'event' },
								{ label: __( 'Kurs', 'review-captain' ), value: 'course' },
								{ label: __( 'Gutschein', 'review-captain' ), value: 'voucher' },
								{ label: __( 'App', 'review-captain' ), value: 'app' }
							],
							onChange: function ( v ) { set( { contextType: v } ); }
						} ),
						a.contextType && el( TextControl, {
							label: __( 'Kontext-ID', 'review-captain' ),
							help: __( '„current“ verwendet den aktuellen Beitrag.', 'review-captain' ),
							value: a.contextId,
							onChange: function ( v ) { set( { contextId: v } ); }
						} ),
						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__( 'Neue Bewertungen werden je nach Einstellung erst nach Moderation veröffentlicht. Einwilligungs- und Datenschutztext legen Sie unter Einstellungen fest.', 'review-captain' )
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
