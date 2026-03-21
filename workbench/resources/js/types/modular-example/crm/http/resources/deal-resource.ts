import { type AsEnum } from '@tolki/enum';

import { Status } from '../../enums';
import type { StatusType } from '../../../app/enums';
import type { User } from '../../../app/models';
import type { User } from '../../models';

/** Exercises: dual enum conflict — $this->status (App\Enums\Status direct access) vs EnumResource::make($this->crm_status) (Crm\Enums\Status), whenLoaded bare with two different User models (Crm\User + App\User), when conditional. */
export interface DealResource
{
    id: number;
    title: string;
    value: number;
    status: StatusType;
    crm_status: AsEnum<typeof Status>;
    customer?: User;
    admin?: User;
    closed_at?: string;
}
