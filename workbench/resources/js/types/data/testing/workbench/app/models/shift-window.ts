/**
 * When a shift's window opens (a NOT NULL column) and closes (a nullable one), both through one serializing cast
 * whose `get()` is nullable, and when its break starts (NOT NULL) and ends (nullable) through a Castable built on that
 * cast, beside a plain string label (GH-154).
 *
 * @see Workbench\App\Models\ShiftWindow
 */
export interface ShiftWindow
{
    id: number;
    label: string;
    opens_at: string;
    closes_at: string | null;
    break_starts_at: string;
    break_ends_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}
