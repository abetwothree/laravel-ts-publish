import { type AsEnum } from '@tolki/enum';

import { Status } from '../enums';
import type { StatusType } from '../enums';

export interface User
{
    id: number;
    name: string;
    email: string;
    company: string | null;
    status: StatusType;
    created_at: string | null;
    updated_at: string | null;
}

export interface UserResource extends Omit<User, 'status'>
{
    status: AsEnum<typeof Status>;
}

export interface UserAllResource extends UserResource {}
