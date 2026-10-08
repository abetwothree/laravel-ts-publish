import { type AsEnum } from '@tolki/ts';

import { Status as EnumsStatus } from '../../../app/enums';
import { Status as CrmStatus } from '../../enums';
import type { StatusType as EnumsStatusType } from '../../../app/enums';
import type { UserResource as ResourcesUserResource } from '../../../app/http/resources';
import type { User as ModelsUser } from '../../../app/models';
import type { StatusType as CrmStatusType } from '../../enums';
import type { User as CrmUser } from '../../models';
import type { UserResource as CrmUserResource } from '.';

/**
 * Exercises: dual enum conflict — $this->status (App\Enums\Status direct access)
 * vs EnumResource::make($this->crm_status) (Crm\Enums\Status), whenLoaded bare
 * with two different User models (Crm\User + App\User), when conditional,
 * resource wrapping with colliding resource names, dual EnumResource::make,
 * both same-named consts wrapped again inside one inline object (status_pair).
 *
 * @see Workbench\Crm\Http\Resources\DealResource
 */
export interface DealResource
{
    id: number;
    title: string;
    value: string;
    status: EnumsStatusType;
    status_enum: AsEnum<typeof EnumsStatus>;
    crm_status: CrmStatusType;
    crm_enum: AsEnum<typeof CrmStatus>;
    status_pair: { app: AsEnum<typeof EnumsStatus>; crm: AsEnum<typeof CrmStatus> };
    customer?: CrmUser;
    admin?: ModelsUser;
    customer_resource?: CrmUserResource;
    admin_resource?: ResourcesUserResource;
    closed_at?: string | null;
}
