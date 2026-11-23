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
		{ label: __( 'Alle Plattformen', 'review-captain' ), value: '' },
		{ label: __( 'Diese Website', 'review-captain' ), value: 'local' },
		{ label: 'Google', value: 'google' },
		{ label: 'Trustpilot', value: 'trustpilot' },
		{ label: 'Trusted Shops', value: 'trusted_shops' },
		{ label: 'App Store', value: 'app_store' },
		{ label: 'Google Play', value: 'google_play' },
		{ label: __( 'Andere Plattform', 'review-captain' ), value: 'other' }
	];

	var contexts = [
		{ label: __( 'Kein Kontext', 'review-captain' ), value: '' },
		{ label: __( 'Beitrag/Seite', 'review-captain' ), value: 'post' },
		{ label: __( 'Produkt', 'review-captain' ), value: 'product' },
		{ label: __( 'Event', 'review-captain' ), value: 'event' },
		{ label: __( 'Kurs', 'review-captain' ), value: 'course' },
		{ label: __( 'Gutschein', 'review-captain' ), value: 'voucher' },
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
		{ label: __( 'Wie in den Einstellungen', 'review-captain' ), value: '' },
		{ label: __( 'Anzeigen', 'review-captain' ), value: 'on' },
		{ label: __( 'Ausblenden', 'review-captain' ), value: 'off' }
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
						{ title: __( 'Datenquelle', 'review-captain' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Plattform', 'review-captain' ),
							value: a.platform,
							options: platforms,
							onChange: function ( v ) { set( { platform: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Typ', 'review-captain' ),
							value: a.kind,
							options: [
								{ label: __( 'Bewertungen und Erfahrungsberichte', 'review-captain' ), value: 'all' },
								{ label: __( 'Nur Bewertungen', 'review-captain' ), value: 'review' },
								{ label: __( 'Nur Erfahrungsberichte (Website)', 'review-captain' ), value: 'testimonial' }
							],
							onChange: function ( v ) { set( { kind: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Mindestbewertung', 'review-captain' ),
							value: String( a.minRating || 0 ),
							options: [
								{ label: __( 'Keine', 'review-captain' ), value: '0' },
								{ label: __( 'ab 2 Sternen', 'review-captain' ), value: '2' },
								{ label: __( 'ab 3 Sternen', 'review-captain' ), value: '3' },
								{ label: __( 'ab 4 Sternen', 'review-captain' ), value: '4' },
								{ label: __( 'nur 5 Sterne', 'review-captain' ), value: '5' }
							],
							onChange: function ( v ) { set( { minRating: parseInt( v, 10 ) || 0 } ); }
						} ),
						el( RangeControl, {
							label: __( 'Anzahl', 'review-captain' ),
							help: __( 'Leer = Vorgabe aus den Einstellungen (Darstellung › Widget-Vorgaben).', 'review-captain' ),
							value: a.limit,
							min: 1,
							max: 50,
							allowReset: true,
							onChange: function ( v ) { set( { limit: v || undefined } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Blättern erlauben', 'review-captain' ),
							checked: !! a.paginate,
							onChange: function ( v ) { set( { paginate: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Kontext', 'review-captain' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Kontexttyp', 'review-captain' ),
							value: a.contextType,
							options: contexts,
							onChange: function ( v ) { set( { contextType: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Kontext-ID', 'review-captain' ),
							help: __( '„current“ verwendet den aktuellen Beitrag.', 'review-captain' ),
							value: a.contextId,
							onChange: function ( v ) { set( { contextId: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Layout', 'review-captain' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Darstellung', 'review-captain' ),
							value: a.layout || '',
							options: [
								{ label: __( 'Vorgabe aus den Einstellungen', 'review-captain' ), value: '' },
								{ label: __( 'Raster', 'review-captain' ), value: 'grid' },
								{ label: __( 'Liste', 'review-captain' ), value: 'list' }
							],
							onChange: function ( v ) { set( { layout: v || undefined } ); }
						} ),
						'list' !== a.layout && el( RangeControl, {
							label: __( 'Spalten', 'review-captain' ),
							help: __( 'Leer = Vorgabe aus den Einstellungen.', 'review-captain' ),
							value: a.columns,
							min: 1,
							max: 4,
							allowReset: true,
							onChange: function ( v ) { set( { columns: v || undefined } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Anzeige', 'review-captain' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Quelle bei Website-Bewertungen anzeigen', 'review-captain' ),
							help: __( 'Externe Bewertungen zeigen Quelle und Link zum Original immer an.', 'review-captain' ),
							checked: false !== a.showSource,
							onChange: function ( v ) { set( { showSource: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Datum', 'review-captain' ),
							value: triState( a.showDate ),
							options: triOptions,
							onChange: function ( v ) { set( { showDate: fromTriState( v ) } ); }
						} ),
						el( SelectControl, {
							label: __( 'Autor', 'review-captain' ),
							value: triState( a.showAuthor ),
							options: triOptions,
							onChange: function ( v ) { set( { showAuthor: fromTriState( v ) } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Zusammenfassung je Plattform', 'review-captain' ),
							checked: false !== a.showSummary,
							onChange: function ( v ) { set( { showSummary: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'SEO-Hinweis', 'review-captain' ), initialOpen: false },
						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__( 'Nur auf dieser Website gesammelte Bewertungen können strukturierte Daten erhalten; kein Rich-Result-Versprechen. Externe Bewertungen werden angezeigt, erhalten aber kein Bewertungs-Markup.', 'review-captain' )
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
