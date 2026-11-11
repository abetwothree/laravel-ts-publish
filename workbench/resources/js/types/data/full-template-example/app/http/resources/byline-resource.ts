/**
 * Spreads a helper's keys and then an array that overrides one of them, where PHP keeps the later key (GH-154): the
 * byline's `author` is the title string, not the helper's recipient.
 *
 * @see Workbench\App\Http\Resources\BylineResource
 */
export interface BylineResource
{
    byline: { id: number; author: string };
}
