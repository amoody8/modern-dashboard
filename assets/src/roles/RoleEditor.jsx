/**
 * The role and capability editor.
 *
 * Two things about this screen are deliberate and worth knowing before reading
 * the code.
 *
 * It shows what a capability *does*, not what it is called. WordPress's names
 * are not self-explanatory and one is an outright trap: revoking `edit_posts`
 * does not stop somebody editing a published post, because that maps to
 * `edit_published_posts`. So capabilities are grouped by intent, families are
 * kept together, and the group carries the warning.
 *
 * And nothing is saved without showing its blast radius first. The other
 * safeguards recover from a mistake; the preview is the one that prevents it.
 */

import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api } from '../lib/api';
import { Checkbox, Notice, Spinner } from '../components/Primitives';
import { PageHeader, Panel } from '../components/Surfaces';
import CapabilityGroup from './CapabilityGroup';
import ImpactPreview from './ImpactPreview';

export default function RoleEditor() {
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ role, setRole ] = useState( '' );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ previewing, setPreviewing ] = useState( false );

	const load = useCallback( () => {
		api.roles()
			.then( ( payload ) => {
				setData( payload );
				setDraft( payload.rules );
				setRole(
					( current ) => current || payload.roles[ 0 ]?.slug || ''
				);
				setError( null );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__( 'Could not load roles.', 'modern-dashboard' )
				)
			);
	}, [] );

	useEffect( load, [ load ] );

	const mutate = useCallback( ( updater ) => {
		setDraft( ( current ) => updater( current ) );
		setDirty( true );
		setStatus( null );
		// Any edit invalidates the preview; showing a stale impact next to
		// changed checkboxes would be worse than showing none.
		setPreview( null );
	}, [] );

	/** The rule for the selected role, always a complete shape. */
	const rule = useMemo( () => {
		const stored = draft?.roles?.[ role ];

		return {
			grant: stored?.grant || [],
			revoke: stored?.revoke || [],
		};
	}, [ draft, role ] );

	const currentRole = useMemo(
		() => data?.roles?.find( ( r ) => r.slug === role ) || null,
		[ data, role ]
	);

	const toggle = useCallback(
		( cap, next ) => {
			mutate( ( current ) => {
				const held = currentRole?.caps?.includes( cap ) ?? false;
				const rules = { ...( current.roles || {} ) };
				const existing = rules[ role ] || { grant: [], revoke: [] };

				const grant = existing.grant.filter( ( c ) => c !== cap );
				const revoke = existing.revoke.filter( ( c ) => c !== cap );

				// Only record a difference from what the role already has.
				// Storing "grant a capability it already holds" would mean the
				// rule survives a plugin later removing it, which is not what
				// anybody ticking the box meant.
				if ( next && ! held ) {
					grant.push( cap );
				}

				if ( ! next && held ) {
					revoke.push( cap );
				}

				if ( 0 === grant.length && 0 === revoke.length ) {
					delete rules[ role ];
				} else {
					rules[ role ] = { grant, revoke };
				}

				return { ...current, roles: rules };
			} );
		},
		[ mutate, role, currentRole ]
	);

	const runPreview = useCallback( () => {
		setPreviewing( true );

		api.previewRoles( draft )
			.then( setPreview )
			.catch( ( err ) =>
				setError(
					err.message ||
						__(
							'Could not work out the impact.',
							'modern-dashboard'
						)
				)
			)
			.finally( () => setPreviewing( false ) );
	}, [ draft ] );

	const save = useCallback( () => {
		setSaving( true );

		api.saveRoles( draft )
			.then( ( payload ) => {
				// Adopt what the server stored: the sanitizer refuses to revoke
				// protected capabilities, so the draft and the saved state can
				// legitimately differ.
				setData( payload );
				setDraft( payload.rules );
				setDirty( false );
				setPreview( null );
				setError( null );
				setStatus( __( 'Roles saved.', 'modern-dashboard' ) );
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Saving failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setSaving( false ) );
	}, [ draft ] );

	if ( error && ! data ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! data || ! draft ) {
		return <Spinner label={ __( 'Loading roles…', 'modern-dashboard' ) } />;
	}

	const effective = ( cap ) => {
		if ( rule.grant.includes( cap ) ) {
			return true;
		}

		if ( rule.revoke.includes( cap ) ) {
			return false;
		}

		return currentRole?.caps?.includes( cap ) ?? false;
	};

	const changedFor = ( cap ) =>
		rule.grant.includes( cap ) || rule.revoke.includes( cap );

	return (
		<div className="md-roles">
			<PageHeader
				title={ __( 'Roles', 'modern-dashboard' ) }
				subtitle={ __(
					'Grant and revoke capabilities across every site. Nothing is written to any site’s roles — switching this off restores the network exactly.',
					'modern-dashboard'
				) }
				actions={
					<>
						<button
							type="button"
							className="md-button md-button--ghost"
							onClick={ runPreview }
							disabled={ previewing || ! dirty }
						>
							{ previewing
								? __( 'Checking…', 'modern-dashboard' )
								: __( 'Check impact', 'modern-dashboard' ) }
						</button>
						<button
							type="button"
							className="md-button"
							onClick={ save }
							disabled={ saving || ! dirty }
						>
							{ saving
								? __( 'Saving…', 'modern-dashboard' )
								: __( 'Save', 'modern-dashboard' ) }
						</button>
					</>
				}
			/>

			{ status && <Notice tone="good">{ status }</Notice> }
			{ error && <Notice tone="bad">{ error }</Notice> }

			<Panel title={ __( 'Apply role edits', 'modern-dashboard' ) }>
				<Checkbox
					label={ __(
						'Apply these capability rules across the network',
						'modern-dashboard'
					) }
					checked={ Boolean( draft.enabled ) }
					onChange={ ( event ) =>
						mutate( ( current ) => ( {
							...current,
							enabled: event.target.checked,
						} ) )
					}
				/>

				<p className="md-field__help">
					{ sprintf(
						/* translators: %s: query argument, e.g. mdash-caps. */
						__(
							'Network administrators are never affected, and adding ?%s=off to any admin URL suspends the rules for one page load.',
							'modern-dashboard'
						),
						data.bypassArg
					) }
				</p>
			</Panel>

			{ preview && <ImpactPreview preview={ preview } /> }

			<Panel
				title={ __( 'Capabilities', 'modern-dashboard' ) }
				actions={
					<select
						value={ role }
						onChange={ ( event ) => setRole( event.target.value ) }
						aria-label={ __( 'Role to edit', 'modern-dashboard' ) }
					>
						{ data.roles.map( ( r ) => (
							<option key={ r.slug } value={ r.slug }>
								{ r.label }
							</option>
						) ) }
					</select>
				}
			>
				{ data.catalogue.empty && (
					<Notice tone="info">
						{ __(
							'Only WordPress’s own capabilities are listed so far. Any a plugin adds will appear once somebody visits the admin of a site where it is active.',
							'modern-dashboard'
						) }
					</Notice>
				) }

				{ data.catalogue.groups.map( ( group ) => (
					<CapabilityGroup
						key={ group.key }
						group={ group }
						isOn={ effective }
						isChanged={ changedFor }
						onToggle={ toggle }
					/>
				) ) }
			</Panel>

			<p className="md-roles__hint">
				{ _n(
					'One capability is protected and cannot be revoked.',
					'Some capabilities are protected and cannot be revoked — removing them would take away the way back in.',
					data.protected.length,
					'modern-dashboard'
				) }
			</p>
		</div>
	);
}
