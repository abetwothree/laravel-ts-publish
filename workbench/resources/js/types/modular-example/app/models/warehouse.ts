import { type AsEnum } from '@tolki/enum';

import { Status as CrmStatus } from '../../crm/enums';
import { Color, Priority, Status as AppStatus } from '../enums';
import type { MenuSettingsType } from '@js/types/settings';
import type { Auditable } from '@/types/audit';
import type { HasTimestamps } from '@/types/common';
import type { StatusType as CrmStatusType } from '../../crm/enums';
import type { User as CrmUser } from '../../crm/models';
import type { ColorType, PriorityType, StatusType as AppStatusType } from '../enums';
import type { Coordinate } from '../value-objects';
import type { User as ManagerUser } from '.';

export interface Warehouse extends HasTimestamps, Pick<Auditable, "created_by" | "updated_by">
{
    id: number;
    name: string;
    /** Write-only accessor on DB column 'phone' — normalizes on set, no get */
    phone: string | null;
    coordinate_data: Coordinate | null;
    status: AppStatusType | null;
    color: ColorType | null;
    priority: PriorityType | null;
    manager_id: number | null;
    primary_contact_id: number | null;
    secondary_contact_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    /** Non-column accessor returning a plain class (Coordinate) */
    location: Coordinate;
    /** Non-column accessor returning CRM Status enum — creates name conflict with column 'status' */
    current_crm_status: CrmStatusType | null;
}

export interface WarehouseResource extends Omit<Warehouse, 'status' | 'color' | 'priority' | 'current_crm_status'>
{
    status: AsEnum<typeof AppStatus> | null;
    color: AsEnum<typeof Color> | null;
    priority: AsEnum<typeof Priority> | null;
    current_crm_status: AsEnum<typeof CrmStatus> | null;
}

export interface WarehouseMutators
{
    /** Non-column accessor returning a TsType class (MenuSettings) with custom import */
    menu_config: MenuSettingsType | null;
    last_user_activity_by: CrmUser | ManagerUser | null;
    last_user_activity_by_typed: CrmUser | ManagerUser | null;
    last_user_activity_by_typed_short: CrmUser | ManagerUser | null;
    review_priority: AppStatusType | PriorityType | null;
    review_priority_typed: AppStatusType | PriorityType | null;
    review_priority_typed_short: AppStatusType | PriorityType | null;
}

export interface WarehouseMutatorsResource extends Omit<WarehouseMutators, 'review_priority' | 'review_priority_typed' | 'review_priority_typed_short'>
{
    review_priority: AsEnum<typeof AppStatus> | null;
    review_priority_typed: AsEnum<typeof AppStatus> | null;
    review_priority_typed_short: AsEnum<typeof AppStatus> | null;
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

export interface WarehouseAllResource extends WarehouseResource, WarehouseMutatorsResource, WarehouseRelations {}
