/**
 * A `new` of a class that is not a resource, in place and as a parameter default.
 *
 * @see Workbench\App\Http\Resources\PostNewValueResource
 */
export interface PostNewValueResource
{
    error: Record<string, never>;
    markup: Record<string, never>;
    opened: { date: string; timezone_type: number; timezone: string };
    opened_immutable: { date: string; timezone_type: number; timezone: string };
    stamped: string;
    dock: string;
    reading: { date: string; timezone_type: number; timezone: string };
    reading_returned: { date: string; timezone_type: number; timezone: string };
    error_default?: Record<string, never>;
    opened_default?: { date: string; timezone_type: number; timezone: string };
    stamped_default?: string;
    dock_default?: string;
    reading_default?: { date: string; timezone_type: number; timezone: string };
}
