/**
 * The one data table.
 *
 * Driven by a column definition rather than hand-written markup, so every table
 * in the plugin sorts, aligns, and truncates the same way — and a new subsystem
 * gets a correct table by describing its columns, not by copying a component.
 *
 * A column is:
 *
 *     {
 *       key:      'users',                    // matches the sort key the API takes
 *       label:    __( 'Users' ),
 *       align:    'end',                      // 'start' (default) | 'end'
 *       sortable: true,
 *       width:    '120px',                    // optional fixed width
 *       render:   ( row ) => <span>…</span>,  // defaults to row[key]
 *     }
 */

import { __ } from '@wordpress/i18n';

/**
 * @param {boolean} active Whether this column is the sorted one.
 * @param {string}  order  'asc' or 'desc'.
 *
 * @return {string|undefined} The aria-sort value, or undefined when unsorted.
 */
function ariaSort( active, order ) {
	if ( ! active ) {
		return undefined;
	}

	return 'asc' === order ? 'ascending' : 'descending';
}

/**
 * @param {boolean} active Whether this column is the sorted one.
 * @param {string}  order  'asc' or 'desc'.
 *
 * @return {string} The arrow glyph, empty when unsorted.
 */
function sortArrow( active, order ) {
	if ( ! active ) {
		return '';
	}

	return 'asc' === order ? '↑' : '↓';
}

/**
 * @param {Object}   props            Component props.
 * @param {Object[]} props.columns    Column definitions.
 * @param {Object[]} props.rows       Row data.
 * @param {Function} props.rowKey     Returns a stable key for a row.
 * @param {string}   props.orderby    Current sort column.
 * @param {string}   props.order      'asc' or 'desc'.
 * @param {Function} props.onSort     Called with a column key.
 * @param {Function} props.onRowClick Called with a row; makes rows activatable.
 * @param {Function} props.isSelected Returns whether a row is the active one.
 * @param {string}   props.caption    Accessible caption, visually hidden.
 *
 * @return {JSX.Element} The table.
 */
export default function DataTable( {
	columns,
	rows,
	rowKey,
	orderby,
	order = 'asc',
	onSort,
	onRowClick,
	isSelected,
	caption,
} ) {
	const sortable = Boolean( onSort );

	return (
		<div className="md-table-wrap">
			<table className="md-table">
				{ caption && (
					<caption className="screen-reader-text">
						{ caption }
					</caption>
				) }
				<thead>
					<tr>
						{ columns.map( ( column ) => {
							const active = orderby === column.key;
							const canSort =
								sortable && false !== column.sortable;

							return (
								<th
									key={ column.key }
									scope="col"
									style={
										column.width
											? { width: column.width }
											: undefined
									}
									className={ [
										'md-table__head',
										'end' === column.align ? 'is-end' : '',
										active ? 'is-sorted' : '',
									]
										.filter( Boolean )
										.join( ' ' ) }
									aria-sort={ ariaSort( active, order ) }
								>
									{ canSort ? (
										<button
											type="button"
											className="md-table__sort"
											onClick={ () =>
												onSort( column.key )
											}
										>
											{ column.label }
											<span
												className="md-table__arrow"
												aria-hidden="true"
											>
												{ sortArrow( active, order ) }
											</span>
										</button>
									) : (
										column.label
									) }
								</th>
							);
						} ) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => {
						const key = rowKey( row );
						const selected = isSelected ? isSelected( row ) : false;

						return (
							<tr
								key={ key }
								className={ [
									'md-table__row',
									onRowClick ? 'is-activatable' : '',
									selected ? 'is-selected' : '',
								]
									.filter( Boolean )
									.join( ' ' ) }
								// A row that opens a detail panel is activatable from the
								// keyboard too; without this it is mouse-only.
								tabIndex={ onRowClick ? 0 : undefined }
								role={ onRowClick ? 'button' : undefined }
								onClick={
									onRowClick
										? () => onRowClick( row )
										: undefined
								}
								onKeyDown={
									onRowClick
										? ( event ) => {
												if (
													'Enter' === event.key ||
													' ' === event.key
												) {
													event.preventDefault();
													onRowClick( row );
												}
										  }
										: undefined
								}
							>
								{ columns.map( ( column ) => (
									<td
										key={ column.key }
										className={ [
											'md-table__cell',
											'end' === column.align
												? 'is-end'
												: '',
											column.numeric ? 'is-numeric' : '',
										]
											.filter( Boolean )
											.join( ' ' ) }
									>
										{ column.render
											? column.render( row )
											: row[ column.key ] }
									</td>
								) ) }
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}

/**
 * A site's name over its address — the leading cell in most of these tables.
 *
 * @param {Object} props       Component props.
 * @param {string} props.title Primary line.
 * @param {string} props.meta  Secondary line.
 * @param {string} props.tone  Optional status dot tone.
 *
 * @return {JSX.Element} The cell content.
 */
export function PrimaryCell( { title, meta, tone } ) {
	return (
		<span className="md-primary-cell">
			{ tone && (
				<span
					className={ `md-dot md-dot--${ tone }` }
					aria-hidden="true"
				/>
			) }
			<span className="md-primary-cell__text">
				<span className="md-primary-cell__title">{ title }</span>
				{ meta && (
					<span className="md-primary-cell__meta">{ meta }</span>
				) }
			</span>
		</span>
	);
}

/**
 * A proportion, drawn rather than written.
 *
 * @param {Object} props       Component props.
 * @param {number} props.value Current value.
 * @param {number} props.max   Value representing a full bar.
 * @param {string} props.label Accessible description.
 *
 * @return {JSX.Element} The bar.
 */
export function MiniBar( { value, max, label } ) {
	const pct =
		max > 0 ? Math.min( 100, Math.round( ( value / max ) * 100 ) ) : 0;

	return (
		<span
			className="md-minibar"
			role="img"
			aria-label={ label || __( 'Relative value', 'modern-dashboard' ) }
		>
			<span
				className="md-minibar__fill"
				style={ { width: `${ pct }%` } }
			/>
		</span>
	);
}
