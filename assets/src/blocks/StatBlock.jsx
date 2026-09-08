/**
 * A single headline number.
 */

import { StatCard } from '../components/Primitives';
import { readMetric } from '../lib/metrics';

export default function StatBlock( { block, overview } ) {
	const settings = block.settings || {};
	const metric = readMetric( settings.metric || 'sites', overview );

	return (
		<StatCard
			label={ settings.label || metric.label }
			value={ metric.value }
			hint={ metric.hint }
			tone={ metric.tone }
		/>
	);
}
