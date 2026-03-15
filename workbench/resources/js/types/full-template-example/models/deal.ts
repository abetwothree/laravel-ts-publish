import { type AsEnum } from '@tolki/enum';

import { Status as CrmStatus, Status as WorkbenchStatus } from '../enums';
import type { StatusType as CrmStatusType, StatusType as WorkbenchStatusType } from '../enums';
import type { User as AdminUser, User as CustomerUser } from './';

export interface Deal
{
    // Columns
    id: number;
    customer_id: number;
    admin_id: number;
    title: string;
    status: WorkbenchStatusType;
    crm_status: CrmStatusType;
    value: number;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** The CRM customer this deal belongs to. */
    customer: CustomerUser;
    /** The system admin/user managing this deal. */
    admin: AdminUser;
    // Counts
    customer_count: number;
    admin_count: number;
    // Exists
    customer_exists: boolean;
    admin_exists: boolean;
}

export interface DealResource extends Omit<Deal, 'status' | 'crm_status'>
{
    status: AsEnum<typeof WorkbenchStatus>;
    crm_status: AsEnum<typeof CrmStatus>;
}
