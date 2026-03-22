import { type AsEnum } from '@tolki/enum';

import { Status as AppStatus } from '../../../app/enums';
import { Status as CrmStatus } from '../../enums';
import type { StatusType as AppStatusType } from '../../../app/enums';
import type { UserResource as AppUserResource } from '../../../app/http/resources';
import type { User as AppUser } from '../../../app/models';
import type { StatusType as CrmStatusType } from '../../enums';
import type { User as CrmUser } from '../../models';
import type { UserResource as CrmUserResource } from '.';

/** Exercises: dual enum conflict — $this->status (App\Enums\Status direct access) vs EnumResource::make($this->crm_status) (Crm\Enums\Status), whenLoaded bare with two different User models (Crm\User + App\User), when conditional, resource wrapping with colliding resource names, dual EnumResource::make. */
export interface DealResource
{
    id: number;
    title: string;
    value: number;
    status: AppStatusType;
    status_enum: AsEnum<typeof AppStatus>;
    crm_status: CrmStatusType;
    crm_enum: AsEnum<typeof CrmStatus>;
    customer?: CrmUser;
    admin?: AppUser;
    customer_resource?: CrmUserResource;
    admin_resource?: AppUserResource;
    closed_at?: string;
}
