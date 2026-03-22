import { type AsEnum } from '@tolki/enum';

import { Status as CrmStatus, Status as WorkbenchStatus } from '../enums';
import type { StatusType as CrmStatusType, StatusType as WorkbenchStatusType } from '../enums';
import type { User as CrmUser, User as WorkbenchUser } from '../models';
import type { UserResource as CrmUserResource, UserResource as WorkbenchUserResource } from './';

/** Exercises: dual enum conflict — $this->status (App\Enums\Status direct access) vs EnumResource::make($this->crm_status) (Crm\Enums\Status), whenLoaded bare with two different User models (Crm\User + App\User), when conditional, resource wrapping with colliding resource names, dual EnumResource::make. */
export interface DealResource
{
    id: number;
    title: string;
    value: number;
    status: WorkbenchStatusType;
    status_enum: AsEnum<typeof WorkbenchStatus>;
    crm_status: CrmStatusType;
    crm_enum: AsEnum<typeof CrmStatus>;
    customer?: CrmUser;
    admin?: WorkbenchUser;
    customer_resource?: CrmUserResource;
    admin_resource?: WorkbenchUserResource;
    closed_at?: string;
}
