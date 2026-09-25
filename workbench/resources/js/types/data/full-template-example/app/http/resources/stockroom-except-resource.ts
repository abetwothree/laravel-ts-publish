import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '../../models';

/**
 * Filters itself through `$this->except()`, dropping the one accessor that names only the `#[TsType]` class.
 *
 * @see Workbench\App\Http\Resources\StockroomExceptResource
 */
export interface StockroomExceptResource
{
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
    contact: User | MenuSettingsType | null;
    layout: { manager: User | null; settings: MenuSettingsType | null };
    manager: User | null;
}
