/**
 * When a shift's window opens (a NOT NULL column) and closes (a nullable one), both through one serializing cast
 * whose `get()` is nullable (GH-154).
 *
 * @see Workbench\App\Models\ShiftWindow
 */
export interface ShiftWindow
{
    // Columns
    id: number;
    opens_at: string | null;
    closes_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}
