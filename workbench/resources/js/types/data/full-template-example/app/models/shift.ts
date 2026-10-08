/**
 * A work shift, whose resource publishes each value as json_encode() writes it.
 *
 * @see Workbench\App\Models\Shift
 */
export interface Shift
{
    // Columns
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** When the shift clocked in. Model::toArray() hands an old-style getter's DateTime to json_encode() as it is. */
    clocked_at: { date: string; timezone_type: number; timezone: string };
    /** The day the shift starts. Model::toArray() runs serializeDate() on a new-style getter's date. */
    starts_on: string;
}
