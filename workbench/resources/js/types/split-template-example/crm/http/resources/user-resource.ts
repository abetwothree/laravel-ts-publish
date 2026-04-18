import type { StatusType } from '../../enums';

/**
 * @see Workbench\Crm\Http\Resources\UserResource
 */
export interface UserResource
{
    id: number;
    name: string;
    email: string;
    company: string | null;
    status: StatusType;
}
