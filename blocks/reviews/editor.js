/**
 * Review Captain – editor UI for review-captain/reviews (no build step).
 * The block is dynamic: the preview uses ServerSideRender (same PHP renderer as the frontend).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Notice = wp.components.Notice;
	var ServerSideRender = wp.serverSideRender;

	var platforms = [
		{ label: __( 'All platforms', 'review-captain' ), value: '' },
		{ label: __( 'This website', 'review-captain' ), value: 'local' },
		{ label: 'Google', value: 'google' },
		{ label: 'Trustpilot', value: 'trustpilot' },
		{ label: 'Trusted Shops', value: 'trusted_shops' },
		{ label: 'App Store', value: 'app_store' },
		{ label: 'Google Play', value: 'google_play' },
		{ label: __( 'Other platform', 'review-captain' ), value: 'other' }
	];

	var contexts = [
		{ label: __( 'No context', 'review-captain' ), value: '' },
		{ label: __( 'Post/page', 'review-captain' ), value: 'post' },
		{ label: __( 'Product', 'review-captain' ), value: 'product' },
		{ label: __( 'Event', 'review-captain' ), value: 'event' },
		{ label: __( 'Course', 'review-captain' ), value: 'course' },
		{ label: __( 'Voucher', 'review-captain' ), value: 'voucher' },
		{ label: __( 'App', 'review-captain' ), value: 'app' }
	];

	function triState( value ) {
		if ( true === value ) {
			return 'on';
		}
		if ( false === value ) {
			return 'off';
		}
		return '';
	}

	function fromTriState( value ) {
		if ( 'on' === value ) {
			return true;
		}
		if ( 'off' === value ) {
			return false;
		}
		return undefined;
	}

	var triOptions = [
		{ label: __( 'As in settings', 'review-captain' ), value: '' },
		{ label: __( 'Show', 'review-captain' ), value: 'on' },
		{ label: __( 'Hide', 'review-captain' ), value: 'off' }
	];

	wp.blocks.registerBlockType( 'review-captain/reviews', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Data source', 'review-captain' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Platform', 'review-captain' ),
							value: a.platform,
							options: platforms,
							onChange: function ( v ) { set( { platform: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Type', 'review-captain' ),
							value: a.kind,
							options: [
								{ label: __( 'Reviews and testimonials', 'review-captain' ), value: 'all' },
								{ label: __( 'Reviews only', 'review-captain' ), value: 'review' },
								{ label: __( 'Testimonials only (website)', 'review-captain' ), value: 'testimonial' }
							],
							onChange: function ( v ) { set( { kind: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Minimum rating', 'review-captain' ),
							value: String( a.minRating || 0 ),
							options: [
								{ label: __( 'None', 'review-captain' ), value: '0' },
								{ label: __( '2 stars and up', 'review-captain' ), value: '2' },
								{ label: __( '3 stars and up', 'review-captain' ), value: '3' },
								{ label: __( '4 stars and up', 'review-captain' ), value: '4' },
								{ label: __( '5 stars only', 'review-captain' ), value: '5' }
							],
							onChange: function ( v ) { set( { minRating: parseInt( v, 10 ) || 0 } ); }
						} ),
						el( RangeControl, {
							label: __( 'Number', 'review-captain' ),
							help: __( 'Empty = default from the settings (Appearance › Widget defaults).', 'review-captain' ),
							value: a.limit,
							min: 1,
							max: 50,
							allowReset: true,
							onChange: function ( v ) { set( { limit: v || undefined } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Allow pagination', 'review-captain' ),
							checked: !! a.paginate,
							onChange: function ( v ) { set( { paginate: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Context', 'review-captain' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Context type', 'review-captain' ),
							value: a.contextType,
							options: contexts,
							onChange: function ( v ) { set( { contextType: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Context ID', 'review-captain' ),
							help: __( '“current” uses the current post.', 'review-captain' ),
							value: a.contextId,
							onChange: function ( v ) { set( { contextId: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Layout', 'review-captain' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Appearance', 'review-captain' ),
							value: a.layout || '',
							options: [
								{ label: __( 'Default from the settings', 'review-captain' ), value: '' },
								{ label: __( 'Grid', 'review-captain' ), value: 'grid' },
								{ label: __( 'List', 'review-captain' ), value: 'list' }
							],
							onChange: function ( v ) { set( { layout: v || undefined } ); }
						} ),
						'list' !== a.layout && el( RangeControl, {
							label: __( 'Columns', 'review-captain' ),
							help: __( 'Empty = default from the settings.', 'review-captain' ),
							value: a.columns,
							min: 1,
							max: 4,
							allowReset: true,
							onChange: function ( v ) { set( { columns: v || undefined } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Display', 'review-captain' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Show source for website reviews', 'review-captain' ),
							help: __( 'External reviews always show the source and a link to the original.', 'review-captain' ),
							checked: false !== a.showSource,
							onChange: function ( v ) { set( { showSource: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Date', 'review-captain' ),
							value: triState( a.showDate ),
							options: triOptions,
							onChange: function ( v ) { set( { showDate: fromTriState( v ) } ); }
						} ),
						el( SelectControl, {
							label: __( 'Author', 'review-captain' ),
							value: triState( a.showAuthor ),
							options: triOptions,
							onChange: function ( v ) { set( { showAuthor: fromTriState( v ) } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Summary per platform', 'review-captain' ),
							checked: false !== a.showSummary,
							onChange: function ( v ) { set( { showSummary: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'SEO note', 'review-captain' ), initialOpen: false },
						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__( 'Only reviews collected on this website can receive structured data; rich results are not guaranteed. External reviews are displayed but do not receive review markup.', 'review-captain' )
						)
					)
				),
				el( ServerSideRender, { block: 'review-captain/reviews', attributes: a } )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
