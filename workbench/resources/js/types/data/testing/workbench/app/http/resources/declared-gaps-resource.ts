/**
 * Maps contacts where the body cannot type every arm or key the `toArray()` `@return` docblock declares (GH-154): a
 * value with no type beside typed arms, a spread of an array with no element type, a branch that spreads one,
 * out-of-order int keys, and a value with no type beside a member the docblock declares more loosely than the body.
 *
 * @see Workbench\App\Http\Resources\DeclaredGapsResource
 */
export interface DeclaredGapsResource
{
    scores: { score: number | null }[];
    contacts: ({ email: string | null } | null)[];
    rows: { name: string | null; locale: string }[];
    branches: { name: string | null; locale: string }[];
    pairs: [string, number][];
    summary: { label: string; count: number | null };
}
