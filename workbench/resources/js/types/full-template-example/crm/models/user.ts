import { type AsEnum } from '@tolki/ts';

import { Status } from '../enums';
import type { Image } from '../../app/models';
import type { StatusType } from '../enums';

/**
 * @see Workbench\Crm\Models\User
 */
export interface User
{
    // Columns
    id: number;
    name: string;
    email: string;
    company: string | null;
    status: StatusType;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    images: Image[];
    // Counts
    images_count: number;
    // Exists
    images_exists: boolean;
}

export interface UserResource extends Omit<User, 'status'>
{
    status: AsEnum<typeof Status>;
}
