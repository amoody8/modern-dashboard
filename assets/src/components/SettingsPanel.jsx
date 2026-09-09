/**
 * Network settings. These are the values every site in the network inherits.
 */

import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Checkbox, Field, Notice, Spinner } from './Primitives';
import { api } from '../lib/api';
import { intervalLabels } from '../lib/labels';
import { formatRelative } from '../lib/format';
import { Panel } from './Surfaces';

export default function SettingsPanel( { onSaved } ) {
	const [ settings, setSettings ] = useState( null );
	const [ intervals, setIntervals ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ nextRun, setNextRun ] = useState( null );

	useEffect( () => {
		api.settings()
			.then( ( data ) => {
				setSettings( data.settings );
				setIntervals( data.intervals );
			} )
			.catch( ( err ) =>
				setError(
					err.message ||
						__( 'Could not load settings.', 'modern-dashboard' )
				)
			);
	}, [] );

	const set = ( key, value ) =>
		setSettings( ( current ) => ( { ...current, [ key ]: value } ) );

	const save = ( event ) => {
		event.preventDefault();
		setSaving( true );
		setStatus( null );
		setError( null );

		api.saveSettings( settings )
			.then( ( data ) => {
				setSettings( data.settings );
				setNextRun( data.next_run );
				setStatus( __( 'Settings saved.', 'modern-dashboard' ) );
				onSaved( data.settings );
			} )
			.catch( ( err ) =>
				setError(
					err.message || __( 'Saving failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setSaving( false ) );
	};

	if ( error && ! settings ) {
		return <Notice tone="bad">{ error }</Notice>;
	}

	if ( ! settings ) {
		return <Spinner />;
	}

	const labels = intervalLabels();

	const nextRunNote = nextRun
		? ` ${ sprintf(
				/* translators: %s: relative time such as "in 12 minutes" */
				__( 'Next refresh %s.', 'modern-dashboard' ),
				formatRelative( nextRun )
		  ) }`
		: '';

	return (
		<form className="md-settings" onSubmit={ save }>
			{ status && (
				<Notice tone="good" onDismiss={ () => setStatus( null ) }>
					{ status }
					{ nextRunNote }
				</Notice>
			) }
			{ error && (
				<Notice tone="bad" onDismiss={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Panel title={ __( 'Collection', 'modern-dashboard' ) }>
				<p className="md-panel__intro">
					{ __(
						'Metrics are gathered in the background, a slice of the network at a time. Larger batches keep data fresher; smaller batches are gentler on a busy server.',
						'modern-dashboard'
					) }
				</p>

				<div className="md-form-grid">
					<Field
						label={ __( 'Refresh interval', 'modern-dashboard' ) }
					>
						{ ( id ) => (
							<select
								id={ id }
								value={ settings.refresh_interval }
								onChange={ ( event ) =>
									set(
										'refresh_interval',
										event.target.value
									)
								}
							>
								{ intervals.map( ( value ) => (
									<option key={ value } value={ value }>
										{ labels[ value ] || value }
									</option>
								) ) }
							</select>
						) }
					</Field>

					<Field
						label={ __( 'Sites per batch', 'modern-dashboard' ) }
					>
						{ ( id ) => (
							<input
								id={ id }
								type="number"
								min="1"
								max="200"
								value={ settings.batch_size }
								onChange={ ( event ) =>
									set(
										'batch_size',
										Number( event.target.value )
									)
								}
							/>
						) }
					</Field>

					<Field
						label={ __(
							'Treat data as stale after (seconds)',
							'modern-dashboard'
						) }
					>
						{ ( id ) => (
							<input
								id={ id }
								type="number"
								min="300"
								max="86400"
								step="300"
								value={ settings.stale_after }
								onChange={ ( event ) =>
									set(
										'stale_after',
										Number( event.target.value )
									)
								}
							/>
						) }
					</Field>

					<Field
						label={ __(
							'Flag a site inactive after (days)',
							'modern-dashboard'
						) }
					>
						{ ( id ) => (
							<input
								id={ id }
								type="number"
								min="1"
								max="3650"
								value={ settings.inactive_threshold_days }
								onChange={ ( event ) =>
									set(
										'inactive_threshold_days',
										Number( event.target.value )
									)
								}
							/>
						) }
					</Field>
				</div>
			</Panel>

			<Panel title={ __( 'Storage scanning', 'modern-dashboard' ) }>
				<p className="md-panel__intro">
					{ __(
						'Measuring an uploads directory means walking every file in it. On networks with large media libraries this is the most expensive thing collected, so it is bounded per site and can be turned off entirely.',
						'modern-dashboard'
					) }
				</p>

				<Checkbox
					label={ __(
						'Measure uploads directory size',
						'modern-dashboard'
					) }
					checked={ settings.collect_storage }
					onChange={ ( event ) =>
						set( 'collect_storage', event.target.checked )
					}
				/>

				<Field
					label={ __(
						'Per-site scan budget (seconds)',
						'modern-dashboard'
					) }
				>
					{ ( id ) => (
						<input
							id={ id }
							type="number"
							min="1"
							max="60"
							disabled={ ! settings.collect_storage }
							value={ settings.storage_scan_timeout }
							onChange={ ( event ) =>
								set(
									'storage_scan_timeout',
									Number( event.target.value )
								)
							}
						/>
					) }
				</Field>
			</Panel>

			<Panel title={ __( 'Access', 'modern-dashboard' ) }>
				<Checkbox
					label={ __(
						'Let site administrators see their own site’s metrics under Dashboard → Site Metrics',
						'modern-dashboard'
					) }
					checked={ settings.allow_site_admins }
					onChange={ ( event ) =>
						set( 'allow_site_admins', event.target.checked )
					}
				/>

				<Checkbox
					label={ __(
						'Restyle the WordPress admin — applies the dashboard\u2019s look to the sidebar, toolbar, list tables and forms on every screen',
						'modern-dashboard'
					) }
					checked={ settings.skin_enabled }
					onChange={ ( event ) =>
						set( 'skin_enabled', event.target.checked )
					}
				/>

				<Checkbox
					label={ __(
						'Enable the command palette — Cmd/Ctrl+K on every admin screen, searching sites, content and admin screens. Network-wide content search stays limited to network administrators.',
						'modern-dashboard'
					) }
					checked={ settings.palette_enabled }
					onChange={ ( event ) =>
						set( 'palette_enabled', event.target.checked )
					}
				/>

				<Field
					label={ __( 'Excluded site IDs', 'modern-dashboard' ) }
					help={ __(
						'These sites are skipped entirely — never collected, never counted.',
						'modern-dashboard'
					) }
				>
					{ ( id ) => (
						<input
							id={ id }
							type="text"
							inputMode="numeric"
							value={ settings.excluded_sites.join( ', ' ) }
							placeholder="12, 34"
							onChange={ ( event ) =>
								set(
									'excluded_sites',
									event.target.value
										.split( ',' )
										.map( ( part ) =>
											parseInt( part.trim(), 10 )
										)
										.filter(
											( value ) =>
												Number.isInteger( value ) &&
												value > 0
										)
								)
							}
						/>
					) }
				</Field>
			</Panel>

			<p>
				<button
					type="submit"
					className="button button-primary"
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'modern-dashboard' )
						: __( 'Save settings', 'modern-dashboard' ) }
				</button>
			</p>
		</form>
	);
}
