import { type AsEnum } from '@tolki/enum';

import { Role } from '../enums';
import type { Profile } from '../models';
import type { PostResource } from './';

/** User account resource. */
export interface UserResource
{
    id: number;
    name: string;
    email: string;
    role: AsEnum<typeof Role> | null;
    profile?: Profile | null;
    posts?: PostResource[];
    phone?: string | null;
    avatar?: string | null;
    posts_count?: number;
    comments_count?: number;
}
