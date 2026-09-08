/**
 * Branding: admin chrome, login screen and white-label text.
 *
 * Scope is either the network default or one site's override. An override
 * replaces the network theme rather than merging into it, so a site either
 * follows the network or has branding of its own — there is no half-inherited
 * state to reason about.
 */

import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Checkbox, Field, Notice, Spinner } from '../components/Primitives';
import ColourField from './ColourField';
import ThemePreview from './ThemePreview';
import { api } from '../lib/api';
import { contrastRatio, contrastVerdict } from '../lib/contrast';

const NETWORK = 'network';

const COLOUR_LABELS = () => ( {
	menu_bg: __( 'Menu background', 'modern-dashboard' ),
	menu_text: __( 'Menu text', 'modern-dashboard' ),
	menu_active_bg: __( 'Current item background', 'modern-dashboard' ),
	menu_active_text: __( 'Current item text', 'modern-dashboard' ),
	bar_bg: __( 'Admin bar background', 'modern-dashboard' ),
	bar_text: __( 'Admin bar text', 'modern-dashboard' ),
	accent: __( 'Primary button', 'modern-dashboard' ),
	link: __( 'Links', 'modern-dashboard' ),
	login_bg: __( 'Login page background', 'modern-dashboard' ),
	login_card_bg: __( 'Login form background', 'modern-dashboard' ),
} );

function ContrastReport( { theme, pairs } ) {
	const results = useMemo(
		() =>
			pairs.map( ( pair ) => ( {
				...pair,
				verdict: contrastVerdict(
					contrastRatio( theme[ pair.fg ], theme[ pair.bg ] )
				),
			} ) ),
		[ theme, pairs ]
	);

	return (
		<ul className="md-contrast">
			{ results.map( ( result ) => (
				<li
					key={ result.label }
					className={ `md-contrast__row is-${ result.verdict?.tone }` }
				>
					<span
						className="md-contrast__swatch"
						style={ {
							background: theme[ result.bg ],
							color: theme[ result.fg ],
						} }
						aria-hidden="true"
					>
						Aa
					</span>
					<span className="md-contrast__label">{ result.label }</span>
					<span className="md-contrast__verdict">
						{ result.verdict
							? result.verdict.message
							: __(
									'Enter a valid hex colour',
									'modern-dashboard'
							  ) }
					</span>
				</li>
			) ) }
		</ul>
	);
}

export default function ThemeEditor() {
	const [ defaults, setDefaults ] = useState( null );
	const [ colours, setColours ] = useState( [] );
	const [ pairs, setPairs ] = useState( [] );
	const [ bypassArg, setBypassArg ] = useState( 'mdash-theme' );

	const [ scope, setScope ] = useState( NETWORK );
	const [ sites, setSites ] = useState( [] );
	const [ search, setSearch ] = useState( '' );

	const [ theme, setTheme ] = useState( null );
	const [ inherits, setInherits ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		api.theme()
			.then( ( data ) => {
				setTheme( data.theme );
				setDefaults( data.defaults );
				setColours( data.colours || [] );
				setPairs( data.pairs || [] );
				setBypassArg( data.bypass_arg || 'mdash-theme' );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__( 'Could not load branding.', 'modern-dashboard' )
				)
			);
	}, [] );

	useEffect( () => {
		const timer = setTimeout( () => {
			api.sites( { search, per_page: 50, orderby: 'name' } )
				.then( ( data ) => setSites( data.items || [] ) )
				.catch( () => setSites( [] ) );
		}, 250 );

		return () => clearTimeout( timer );
	}, [ search ] );

	const loadScope = useCallback( ( next ) => {
		setScope( next );
		setStatus( null );
		setError( null );
		setDirty( false );

		if ( next === NETWORK ) {
			api.theme()
				.then( ( data ) => {
					setTheme( data.theme );
					setInherits( false );
				} )
				.catch( ( err ) => setError( err.message ) );

			return;
		}

		api.siteTheme( next )
			.then( ( data ) => {
				// A site with no override is shown the network theme as a
				// read-only starting point until it is given one.
				setTheme( data.theme || data.network );
				setInherits( data.inherits );
			} )
			.catch( ( err ) => setError( err.message ) );
	}, [] );

	const set = ( key, value ) => {
		setTheme( ( current ) => ( { ...current, [ key ]: value } ) );
		setDirty( true );
		setStatus( null );
	};

	const save = () => {
		setSaving( true );
		setError( null );

		const request =
			scope === NETWORK
				? api.saveTheme( theme )
				: api.saveSiteTheme( scope, theme );

		request
			.then( ( data ) => {
				setTheme( data.theme );
				setInherits( Boolean( data.inherits ) );
				setDirty( false );
				setStatus( __( 'Branding saved.', 'modern-dashboard' ) );
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Saving failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setSaving( false ) );
	};

	const clearOverride = () => {
		setError( null );

		api.clearSiteTheme( scope )
			.then( () => {
				loadScope( scope );
				setStatus(
					__(
						'This site follows the network branding again.',
						'modern-dashboard'
					)
				);
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__(
							'Could not clear the override.',
							'modern-dashboard'
						)
				)
			);
	};

	const resetToDefaults = () => {
		setTheme( ( current ) => ( {
			...defaults,
			enabled: current.enabled,
		} ) );
		setDirty( true );
	};

	if ( error && ! theme ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! theme ) {
		return (
			<Spinner label={ __( 'Loading branding…', 'modern-dashboard' ) } />
		);
	}

	const labels = COLOUR_LABELS();
	const isSite = scope !== NETWORK;

	return (
		<div className="md-branding">
			{ status && (
				<Notice tone="good" onDismiss={ () => setStatus( null ) }>
					{ status }
				</Notice>
			) }
			{ error && (
				<Notice tone="bad" onDismiss={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<div className="md-builder__bar">
				<Field label={ __( 'Branding for', 'modern-dashboard' ) }>
					{ ( id ) => (
						<select
							id={ id }
							value={ scope }
							onChange={ ( event ) =>
								loadScope( event.target.value )
							}
						>
							<option value={ NETWORK }>
								{ __( 'Network default', 'modern-dashboard' ) }
							</option>
							{ sites.map( ( site ) => (
								<option
									key={ site.blog_id }
									value={ site.blog_id }
								>
									{ site.name }
								</option>
							) ) }
						</select>
					) }
				</Field>

				<Field label={ __( 'Find a site', 'modern-dashboard' ) }>
					{ ( id ) => (
						<input
							id={ id }
							type="search"
							value={ search }
							placeholder={ __(
								'Search sites…',
								'modern-dashboard'
							) }
							onChange={ ( event ) =>
								setSearch( event.target.value )
							}
						/>
					) }
				</Field>

				<div className="md-builder__actions">
					<button
						type="button"
						className="button"
						onClick={ resetToDefaults }
					>
						{ __( 'Reset colours', 'modern-dashboard' ) }
					</button>
					{ isSite && ! inherits && (
						<button
							type="button"
							className="button md-danger"
							onClick={ clearOverride }
						>
							{ __( 'Follow network', 'modern-dashboard' ) }
						</button>
					) }
					<button
						type="button"
						className="button button-primary"
						onClick={ save }
						disabled={ saving || ! dirty }
					>
						{ saving
							? __( 'Saving…', 'modern-dashboard' )
							: __( 'Save', 'modern-dashboard' ) }
					</button>
				</div>
			</div>

			{ isSite && inherits && (
				<Notice tone="info">
					{ __(
						'This site follows the network branding. Changing anything below and saving gives it branding of its own, which replaces the network’s entirely rather than merging with it.',
						'modern-dashboard'
					) }
				</Notice>
			) }

			{ dirty && (
				<p className="md-builder__dirty">
					{ __( 'Unsaved changes.', 'modern-dashboard' ) }
				</p>
			) }

			<div className="md-branding__grid">
				<div>
					<section className="md-panel">
						<header className="md-panel__header">
							<h2>
								{ __( 'Admin chrome', 'modern-dashboard' ) }
							</h2>
						</header>

						<Checkbox
							label={
								isSite
									? __(
											'Apply this branding to this site',
											'modern-dashboard'
									  )
									: __(
											'Apply this branding across the network',
											'modern-dashboard'
									  )
							}
							checked={ theme.enabled }
							onChange={ ( event ) =>
								set( 'enabled', event.target.checked )
							}
						/>

						<div className="md-colours">
							{ colours
								.filter(
									( key ) => ! key.startsWith( 'login_' )
								)
								.map( ( key ) => (
									<ColourField
										key={ key }
										label={ labels[ key ] || key }
										value={ theme[ key ] }
										onChange={ ( value ) =>
											set( key, value )
										}
									/>
								) ) }
						</div>

						<Field
							label={ __(
								'Corner radius (px)',
								'modern-dashboard'
							) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="number"
									min="0"
									max="24"
									value={ theme.radius }
									onChange={ ( event ) =>
										set(
											'radius',
											Number( event.target.value )
										)
									}
								/>
							) }
						</Field>
					</section>

					<section className="md-panel">
						<header className="md-panel__header">
							<h2>
								{ __( 'Login screen', 'modern-dashboard' ) }
							</h2>
						</header>

						<div className="md-colours">
							{ colours
								.filter( ( key ) => key.startsWith( 'login_' ) )
								.map( ( key ) => (
									<ColourField
										key={ key }
										label={ labels[ key ] || key }
										value={ theme[ key ] }
										onChange={ ( value ) =>
											set( key, value )
										}
									/>
								) ) }
						</div>

						<Field
							label={ __( 'Logo URL', 'modern-dashboard' ) }
							help={ __(
								'An https:// image URL. Leave empty for the WordPress logo.',
								'modern-dashboard'
							) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="url"
									value={ theme.login_logo }
									placeholder="https://example.com/logo.png"
									onChange={ ( event ) =>
										set( 'login_logo', event.target.value )
									}
								/>
							) }
						</Field>

						<Field
							label={ __( 'Logo size (px)', 'modern-dashboard' ) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="number"
									min="24"
									max="480"
									value={ theme.login_logo_width }
									onChange={ ( event ) =>
										set(
											'login_logo_width',
											Number( event.target.value )
										)
									}
								/>
							) }
						</Field>

						<Field
							label={ __( 'Logo links to', 'modern-dashboard' ) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="url"
									value={ theme.login_url }
									placeholder="https://example.com"
									onChange={ ( event ) =>
										set( 'login_url', event.target.value )
									}
								/>
							) }
						</Field>

						<Field
							label={ __(
								'Logo title text',
								'modern-dashboard'
							) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="text"
									value={ theme.login_title }
									onChange={ ( event ) =>
										set( 'login_title', event.target.value )
									}
								/>
							) }
						</Field>
					</section>

					<section className="md-panel">
						<header className="md-panel__header">
							<h2>{ __( 'White-label', 'modern-dashboard' ) }</h2>
						</header>

						<Field
							label={ __(
								'Admin footer text',
								'modern-dashboard'
							) }
							help={ __(
								'Replaces “Thank you for creating with WordPress”.',
								'modern-dashboard'
							) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="text"
									value={ theme.footer_text }
									onChange={ ( event ) =>
										set( 'footer_text', event.target.value )
									}
								/>
							) }
						</Field>

						<Field
							label={ __( 'Greeting', 'modern-dashboard' ) }
							help={ __(
								'Replaces “Howdy,” in the admin bar.',
								'modern-dashboard'
							) }
						>
							{ ( id ) => (
								<input
									id={ id }
									type="text"
									value={ theme.howdy_text }
									placeholder={ __(
										'Howdy,',
										'modern-dashboard'
									) }
									onChange={ ( event ) =>
										set( 'howdy_text', event.target.value )
									}
								/>
							) }
						</Field>

						<Checkbox
							label={ __(
								'Hide the WordPress logo in the admin bar',
								'modern-dashboard'
							) }
							checked={ theme.hide_wp_logo }
							onChange={ ( event ) =>
								set( 'hide_wp_logo', event.target.checked )
							}
						/>
					</section>
				</div>

				<div className="md-branding__side">
					<section className="md-panel">
						<header className="md-panel__header">
							<h2>{ __( 'Preview', 'modern-dashboard' ) }</h2>
						</header>
						<div className="md-panel__pad">
							<ThemePreview theme={ theme } />
						</div>
					</section>

					<section className="md-panel">
						<header className="md-panel__header">
							<h2>{ __( 'Legibility', 'modern-dashboard' ) }</h2>
						</header>
						<div className="md-panel__pad">
							<ContrastReport theme={ theme } pairs={ pairs } />
							<p className="md-field__help">
								{ sprintf(
									/* translators: %s: query argument name, e.g. mdash-theme */
									__(
										'If a colour scheme turns out to be unreadable, adding ?%s=off to any admin URL shows the unbranded admin.',
										'modern-dashboard'
									),
									bypassArg
								) }
							</p>
						</div>
					</section>
				</div>
			</div>
		</div>
	);
}
