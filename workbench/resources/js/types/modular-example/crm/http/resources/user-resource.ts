import type { StatusType } from '../../enums';

export interface UserResource
{
    id: number;
    name: string;
    email: string;
    company: string | null;
    status: StatusType;
}
