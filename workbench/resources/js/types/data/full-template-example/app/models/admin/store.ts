/**
 * @see Workbench\App\Models\Admin\Store
 */
export interface Store
{
    // Columns
    id: number;
    name: string;
    phone: string | null;
    coordinate_data: string | null;
    status: string | null;
    color: number | null;
    priority: number | null;
    manager_id: number | null;
    primary_contact_id: number | null;
    secondary_contact_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}
