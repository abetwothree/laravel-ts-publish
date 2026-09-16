/**
 * Accessors typed only by their getter bodies: literal shapes, helpers, collection pipelines, cycles.
 *
 * @see Workbench\App\Models\Release
 */
export interface Release
{
    // Columns
    id: number;
    major: number;
    minor: number;
    notes: string | null;
    tags_csv: string;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    version_data: { major: number; minor: number };
    label: { full: string; notes: string | null };
    tag_list: { name: string }[];
    channel_options: { "1": string; "2": string };
    channels: string[];
    loop_a: unknown;
    loop_b: unknown;
    /** Loop-built dynamic keys: must stay unknown[], nothing here is statically knowable. */
    dynamic_totals: unknown[];
    /** Old-style accessor with a vague signature and a literal body. */
    summary: { major: number };
}
