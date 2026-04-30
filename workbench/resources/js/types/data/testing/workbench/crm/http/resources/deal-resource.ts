import { type AsEnum } from '@tolki/ts';

import { Status as WorkbenchStatus } from '../../../app/enums';
import { Status as CrmStatus } from '../../enums';
import type { StatusType as WorkbenchStatusType } from '../../../app/enums';
import type { UserResource as WorkbenchUserResource } from '../../../app/http/resources';
import type { User as WorkbenchUser } from '../../../app/models';
import type { StatusType as CrmStatusType } from '../../enums';
import type { User as CrmUser } from '../../models';
import type { UserResource as CrmUserResource } from '.';

/**
 * Exercises: dual enum conflict — $this->status (App\Enums\Status direct access) vs EnumResource::make($this->crm_status) (Crm\Enums\Status), whenLoaded bare with two different User models (Crm\User + App\User), when conditional, resource wrapping with colliding resource names, dual EnumResource::make.
 *
 * @see Workbench\Crm\Http\Resources\DealResource
 */
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
    closed_at?: string | null;
}
