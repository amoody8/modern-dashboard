/**
 * Decides which dashboard each role sees.
 *
 * This is where the network-controlled model becomes concrete: one map, held at
 * the network level, that every site reads and no site can override.
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Field } from '../components/Primitives';
import { api } from '../lib/api';
import { Panel } from '../components/Surfaces';

const INHERIT = '';

export default function AssignmentPanel( {
	templates,
	roles,
	assignments,
	defaultId,
	onSaved,
	onError,
} ) {
	const [ draft, setDraft ] = useState( assignments );
	const [ fallback, setFallback ] = useState( defaultId );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => setDraft( assignments ), [ assignments ] );
	useEffect( () => setFallback( defaultId ), [ defaultId ] );

	const save = () => {
		setSaving( true );

		// Roles left on "use the default" are sent as absent rather than empty,
		// so the stored map only ever holds real assignments.
		const cleaned = Object.fromEntries(
			Object.entries( draft ).filter(
				( [ , value ] ) => value !== INHERIT
			)
		);

		api.saveAssignments( { assignments: cleaned, default: fallback } )
			.then( onSaved )
			.catch( ( err ) =>
				onError(
					err.message ||
						__( 'Saving assignments failed.', 'modern-dashboard' )
				)
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<Panel
			title={ __( 'Who sees what', 'modern-dashboard' ) }
			className="md-assignments"
		>
			<p className="md-panel__intro">
				{ __(
					'Assign a dashboard to a role. Anyone whose role has no assignment gets the default.',
					'modern-dashboard'
				) }
			</p>

			<div className="md-form-grid">
				<Field label={ __( 'Default dashboard', 'modern-dashboard' ) }>
					{ ( id ) => (
						<select
							id={ id }
							value={ fallback }
							onChange={ ( event ) =>
								setFallback( event.target.value )
							}
						>
							{ templates.map( ( template ) => (
								<option
									key={ template.id }
									value={ template.id }
								>
									{ template.name }
								</option>
							) ) }
						</select>
					) }
				</Field>

				{ roles.map( ( role ) => (
					<Field key={ role.value } label={ role.label }>
						{ ( id ) => (
							<select
								id={ id }
								value={ draft[ role.value ] || INHERIT }
								onChange={ ( event ) =>
									setDraft( ( current ) => ( {
										...current,
										[ role.value ]: event.target.value,
									} ) )
								}
							>
								<option value={ INHERIT }>
									{ __(
										'Use the default',
										'modern-dashboard'
									) }
								</option>
								{ templates.map( ( template ) => (
									<option
										key={ template.id }
										value={ template.id }
									>
										{ template.name }
									</option>
								) ) }
							</select>
						) }
					</Field>
				) ) }
			</div>

			<p className="md-assignments__actions">
				<button
					type="button"
					className="button"
					onClick={ save }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'modern-dashboard' )
						: __( 'Save assignments', 'modern-dashboard' ) }
				</button>
			</p>
		</Panel>
	);
}
