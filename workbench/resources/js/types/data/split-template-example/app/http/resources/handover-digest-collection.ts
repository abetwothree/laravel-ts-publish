import type { HandoverSummaryResource } from '.';

/**
 * Stacked on a body-less collection and body-less itself: Laravel collects its own `$collects`, under the inherited
 * `handovers` wrap.
 *
 * @see Workbench\App\Http\Resources\HandoverDigestCollection
 */
export interface HandoverDigestCollection
{
    handovers: HandoverSummaryResource[];
}
