/**
 * Accessors typed only by their getter bodies: literal shapes, helpers, collection pipelines, cycles.
 *
 * @see Workbench\App\Models\Release
 */
export interface Release
{
    id: number;
    major: number;
    minor: number;
    notes: string | null;
    tags_csv: string;
    created_at: string | null;
    updated_at: string | null;
    /** Old-style accessor with a vague signature and a literal body. */
    summary: { major: number };
}

export interface ReleaseMutators
{
    version_data: { major: number; minor: number };
    label: { full: string; notes: string | null };
    tag_list: { name: string }[];
    channel_options: { "1": string; "2": string };
    channels: string[];
    /** An empty literal carries no element information, so the `: array` signature answers instead. */
    empty_list: unknown[];
    /** The `new Attribute(get: ...)` form, which getterClosure() reads like make()/get(). */
    constructed_version: { major: number };
    loop_a: unknown;
    loop_b: unknown;
    /** The same four filters on the model itself, read as a getter body. */
    column_picks: { named: Pick<Release, 'major' | 'minor'>; rest: Pick<Release, 'id' | 'major' | 'minor' | 'created_at' | 'updated_at'>; picked: Record<string, unknown>; left: Record<string, unknown> };
    /** Loop-built dynamic keys: must stay unknown[], nothing here is statically knowable. */
    dynamic_totals: unknown[];
    trait_version: { major: number; label: string };
}

export interface ReleaseAll extends Release, ReleaseMutators {}
