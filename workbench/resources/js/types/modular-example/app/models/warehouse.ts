import { type AsEnum } from '@tolki/enum';

import { Status as CrmStatus } from '../../crm/enums';
import { Status as AppStatus } from '../enums';
import type { MenuSettingsType } from '@js/types/settings';
import type { StatusType as CrmStatusType } from '../../crm/enums';
import type { User as CrmUser } from '../../crm/models';
import type { StatusType as AppStatusType } from '../enums';
import type { Coordinate } from '../value-objects';
import type { User as ManagerUser } from '.';

export interface Warehouse
{
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
}

export interface WarehouseResource extends Omit<Warehouse, 'status'>
{
    status: AsEnum<typeof AppStatus> | null;
}

export interface WarehouseMutators
{
    /** Non-column accessor returning a plain class (Coordinate) */
    location: Coordinate;
    /** Non-column accessor returning a TsType class (MenuSettings) with custom import */
    menu_config: MenuSettingsType | null;
    /** Non-column accessor returning CRM Status enum — creates name conflict with column 'status' */
    current_crm_status: CrmStatusType | null;
}

export interface WarehouseMutatorsResource extends Omit<WarehouseMutators, 'current_crm_status'>
{
    current_crm_status: AsEnum<typeof CrmStatus> | null;
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
