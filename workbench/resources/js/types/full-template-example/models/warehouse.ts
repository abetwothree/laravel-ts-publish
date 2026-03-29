import { type AsEnum } from '@tolki/enum';

import { Status as AppStatus, Status as CrmStatus } from '../enums';
import type { MenuSettingsType } from '@js/types/settings';
import type { Auditable } from '@/types/audit';
import type { HasTimestamps } from '@/types/common';
import type { StatusType as AppStatusType, StatusType as CrmStatusType } from '../enums';
import type { Coordinate, User as CrmUser, User as ManagerUser } from './';

export interface Warehouse extends HasTimestamps, Pick<Auditable, "created_by" | "updated_by">
{
    // Columns
    id: number;
    name: string;
    /** Write-only accessor on DB column 'phone' — normalizes on set, no get */
    phone: string | null;
    coordinate_data: Coordinate | null;
    status: AppStatusType | null;
    manager_id: number | null;
    primary_contact_id: number | null;
    secondary_contact_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** Non-column accessor returning a plain class (Coordinate) */
    location: Coordinate;
    /** Non-column accessor returning a TsType class (MenuSettings) with custom import */
    menu_config: MenuSettingsType | null;
    /** Non-column accessor returning CRM Status enum — creates name conflict with column 'status' */
    current_crm_status: CrmStatusType | null;
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

export interface WarehouseResource extends Omit<Warehouse, 'status' | 'current_crm_status'>
{
    status: AsEnum<typeof AppStatus> | null;
    current_crm_status: AsEnum<typeof CrmStatus> | null;
}
