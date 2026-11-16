/**
 * Review Captain block editor UI (no build step; plain wp.* globals).
 * Rendering is server-side (ServerSideRender) so editor and frontend output are identical.
 */
( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var SSR = wp.serverSideRender;
	var data = window.reviewCaptainBlocks || { widgets: [], layouts: [ 'grid', 'list' ], adminUrl: '' };

	var providerOptions = [
		{ value: 'site', label: __( 'This website', 'review-captain' ) },
		{ value: 'google', label: 'Google' },
		{ value: 'trustpilot', label: 'Trustpilot' },
		{ value: 'etrusted', label: 'Trusted Shops' },
		{ value: 'apple_app_store', label: 'App Store' },
		{ value: 'google_play', label: 'Google Play' },
		{ value: 'other', label: __( 'Other platform', 'review-captain' ) }
	];

	function preview( name, attributes ) {
		return el( SSR, { block: name, attributes: attributes } );
	}

	wp.blocks.registerBlockType( 'review-captain/widget', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var widgetOptions = [ { value: 0, label: __( '— Configure here —', 'review-captain' ) } ].concat( data.widgets );
			var inline = ! a.widgetId;
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Widget', 'review-captain' ), initialOpen: true },
						el( c.SelectControl, {
							label: __( 'Saved widget', 'review-captain' ),
							value: a.widgetId,
							options: widgetOptions,
							onChange: function ( v ) { set( { widgetId: parseInt( v, 10 ) || 0 } ); }
						} ),
						data.adminUrl ? el( c.ExternalLink, { href: data.adminUrl }, __( 'Manage widgets', 'review-captain' ) ) : null
					),
					inline && el(
						c.PanelBody,
						{ title: __( 'Layout', 'review-captain' ) },
						el( c.SelectControl, {
							label: __( 'Layout', 'review-captain' ),
							value: a.layout,
							options: data.layouts.map( function ( l ) { return { value: l, label: l }; } ),
							onChange: function ( v ) { set( { layout: v } ); }
						} ),
						el( c.RangeControl, { label: __( 'Number of reviews', 'review-captain' ), min: 1, max: 24, value: a.limit, onChange: function ( v ) { set( { limit: v } ); } } ),
						el( c.RangeControl, { label: __( 'Columns (0 = automatic)', 'review-captain' ), min: 0, max: 4, value: a.columns, onChange: function ( v ) { set( { columns: v } ); } } )
					),
					inline && el(
						c.PanelBody,
						{ title: __( 'Content', 'review-captain' ) },
						providerOptions.map( function ( o ) {
							return el( c.CheckboxControl, {
								key: o.value,
								label: o.label,
								checked: a.providers.indexOf( o.value ) !== -1,
								onChange: function ( on ) {
									var next = a.providers.filter( function ( p ) { return p !== o.value; } );
									if ( on ) { next.push( o.value ); }
									set( { providers: next } );
								}
							} );
						} ),
						el( c.RangeControl, {
							label: __( 'Minimum rating shown (display filter only)', 'review-captain' ),
							min: 0, max: 5, value: a.ratingMin,
							onChange: function ( v ) { set( { ratingMin: v } ); }
						} ),
						el( c.ToggleControl, { label: __( 'Include testimonials', 'review-captain' ), checked: a.testimonials, onChange: function ( v ) { set( { testimonials: v } ); } } ),
						el( c.TextControl, { label: __( 'Context type (e.g. product)', 'review-captain' ), value: a.contextType, onChange: function ( v ) { set( { contextType: v } ); } } ),
						el( c.TextControl, { label: __( 'Context ID', 'review-captain' ), value: a.contextId, onChange: function ( v ) { set( { contextId: v } ); } } ),
						el( 'p', { className: 'components-base-control__help' }, __( 'The source of external reviews is always shown and cannot be hidden.', 'review-captain' ) )
					)
				),
				preview( 'review-captain/widget', a )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'review-captain/form', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Form', 'review-captain' ) },
						el( c.TextControl, { label: __( 'Context type (e.g. product)', 'review-captain' ), value: a.contextType, onChange: function ( v ) { set( { contextType: v } ); } } ),
						el( c.TextControl, { label: __( 'Context ID', 'review-captain' ), value: a.contextId, onChange: function ( v ) { set( { contextId: v } ); } } )
					)
				),
				preview( 'review-captain/form', a )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'review-captain/cta', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Call to action', 'review-captain' ) },
						el( c.TextControl, { label: __( 'Text', 'review-captain' ), value: a.text, onChange: function ( v ) { set( { text: v } ); } } ),
						el( 'p', { className: 'components-base-control__help' }, __( 'All visitors see the same neutral buttons. No star pre-selection.', 'review-captain' ) )
					)
				),
				preview( 'review-captain/cta', a )
			);
		},
		save: function () { return null; }
	} );
}( window.wp ) );
