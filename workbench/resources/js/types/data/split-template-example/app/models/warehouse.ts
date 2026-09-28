import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../crm/enums';
import { Color, Priority, Status as EnumsStatus } from '../enums';
import type { MenuSettingsType } from '@js/types/settings';
import type { Auditable } from '@/types/audit';
import type { HasTimestamps } from '@/types/common';
import type { StatusType as CrmStatusType } from '../../crm/enums';
import type { User as CrmUser } from '../../crm/models';
import type { ColorType, PriorityType, StatusType as EnumsStatusType } from '../enums';
import type { Image, User as ManagerUser } from '.';

/** @see Workbench\App\Models\Warehouse */
export interface Warehouse extends HasTimestamps, Pick<Auditable, "created_by" | "updated_by">
{
    id: number;
    name: string;
    /** Write-only accessor on DB column 'phone' — normalizes on set, no get */
    phone: string | null;
    coordinate_data: { lat: number; lng: number } | null;
    status: EnumsStatusType | null;
    color: ColorType | null;
    priority: PriorityType | null;
    manager_id: number | null;
    primary_contact_id: number | null;
    secondary_contact_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    /** Non-column accessor returning a plain class (Coordinate) */
    location: { lat: number; lng: number };
    /** Non-column accessor returning CRM Status enum — creates name conflict with column 'status' */
    current_crm_status: CrmStatusType | null;
}

export interface WarehouseResource extends Omit<Warehouse, 'status' | 'color' | 'priority' | 'current_crm_status'>
{
    status: AsEnum<typeof EnumsStatus> | null;
    color: AsEnum<typeof Color> | null;
    priority: AsEnum<typeof Priority> | null;
    current_crm_status: AsEnum<typeof CrmStatus> | null;
}

export interface WarehouseMutators
{
    /** Non-column accessor returning a TsType class (MenuSettings) with custom import */
    menu_config: MenuSettingsType | null;
    last_user_activity_by: CrmUser | ManagerUser | null;
    last_checked_by: Image | ManagerUser | null;
    /** The regional hub this warehouse rolls up to. */
    regional_hub: Warehouse | null;
    last_user_activity_by_typed: CrmUser | ManagerUser | null;
    last_user_activity_by_typed_short: CrmUser | ManagerUser | null;
    review_priority: EnumsStatusType | PriorityType | null;
    review_priority_typed: EnumsStatusType | PriorityType | null;
    review_priority_typed_short: EnumsStatusType | PriorityType | null;
}

export interface WarehouseRelations
{
    // Relations
    manager: ManagerUser | null;
    primary_contact: CrmUser | null;
    secondary_contact: CrmUser | null;
    // Counts
    manager_count: number;
    primary_contact_count: number;
    secondary_contact_count: number;
    // Exists
    manager_exists: boolean;
    primary_contact_exists: boolean;
    secondary_contact_exists: boolean;
}

export interface WarehouseAll extends Warehouse, WarehouseMutators, WarehouseRelations {}

export interface WarehouseAllResource extends WarehouseResource, WarehouseMutators, WarehouseRelations {}
