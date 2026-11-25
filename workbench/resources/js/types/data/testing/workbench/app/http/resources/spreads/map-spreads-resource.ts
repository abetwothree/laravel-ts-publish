/**
 * Spreads of maps and lists that write keys again or add numbered keys (GH-161, GH-153): a map local given a named
 * key over an earlier key, a list spread after a map, and a list spread beside a named key.
 *
 * @see Workbench\App\Http\Resources\Spreads\MapSpreadsResource
 */
export interface MapSpreadsResource
{
    named_key_over_key: { by: { name: string | null; email: string | null }; z: number };
    list_after_map: Record<string, string>;
    list_beside_key: { x: string };
}
