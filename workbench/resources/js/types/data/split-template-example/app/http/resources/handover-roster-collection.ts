import type { HandoverResource } from '.';

/**
 * Declares nothing: the `$collects` it inherits outranks the naming convention, so Laravel collects HandoverResource,
 * not HandoverRosterResource.
 *
 * @see Workbench\App\Http\Resources\HandoverRosterCollection
 */
export interface HandoverRosterCollection
{
    handovers: HandoverResource[];
}
