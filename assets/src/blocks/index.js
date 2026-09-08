/**
 * Maps a block type to the component that renders it.
 *
 * Types come from the PHP registry, so anything registered there without a
 * renderer here falls back to a visible placeholder rather than a blank space —
 * a missing renderer should be obvious, not silent.
 */

import ChartBlock from './ChartBlock';
import StatBlock from './StatBlock';
import { AttentionBlock, SiteListBlock } from './ListBlocks';
import {
	EnvironmentBlock,
	FreshnessBlock,
	HeadingBlock,
	SpacerBlock,
	TextBlock,
} from './InfoBlocks';

export const renderers = {
	stat: StatBlock,
	chart: ChartBlock,
	attention: AttentionBlock,
	site_list: SiteListBlock,
	environment: EnvironmentBlock,
	freshness: FreshnessBlock,
	heading: HeadingBlock,
	text: TextBlock,
	spacer: SpacerBlock,
};

/**
 * @param {string} type Block type.
 * @return {Function|null} The renderer, or null when none is registered.
 */
export function getRenderer( type ) {
	return renderers[ type ] || null;
}
