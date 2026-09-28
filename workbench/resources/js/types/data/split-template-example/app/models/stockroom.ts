import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '.';

/**
 * Accessors that each name one model and a `#[TsType(import:)]` class, for resources that filter this model with
 * only() and except().
 *
 * @see Workbench\App\Models\Stockroom
 */
export interface Stockroom
{
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

export interface StockroomMutators
{
    menu_config: MenuSettingsType | null;
    contact: User | MenuSettingsType | null;
    layout: { manager: User | null; settings: MenuSettingsType | null };
}

export interface StockroomRelations
{
    // Relations
    manager: User | null;
    // Counts
    manager_count: number;
    // Exists
    manager_exists: boolean;
}

export interface StockroomAll extends Stockroom, StockroomMutators, StockroomRelations {}
