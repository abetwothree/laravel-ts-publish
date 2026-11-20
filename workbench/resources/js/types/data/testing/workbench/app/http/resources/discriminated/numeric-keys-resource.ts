/**
 * Numeric keys in groups that follow a sibling key's value (GH-157): one as a dependent, one as the discriminant, at
 * the top level and inside a nested literal. Each keeps its quoted name in every arm.
 *
 * @see Workbench\App\Http\Resources\Discriminated\NumericKeysResource
 */
export type NumericKeysResource = {
    nested: { pinned: true; "0": string; "7": 0; note: string } | { pinned: true; "0": string; "7": 1; note: number } | { pinned: false; "0": null; "7": 0; note: string } | { pinned: false; "0": null; "7": 1; note: number };
} & (
    | { is_pinned: true; "0": string }
    | { is_pinned: false; "0": null }
) & (
    | { "7": 0; note: string }
    | { "7": 1; note: number }
);
