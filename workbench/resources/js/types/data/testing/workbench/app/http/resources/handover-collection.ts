import type { HandoverResource } from '.';

/**
 * A body-less collection with its own wrap key, which every collection stacked on it inherits.
 *
 * @see Workbench\App\Http\Resources\HandoverCollection
 */
export interface HandoverCollection
{
    handovers: HandoverResource[];
}
