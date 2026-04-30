import type { SharedModelInterface } from '@/types/shared-model';

/**
 * @see Workbench\App\Models\BaseSharedExtendableModel
 */
export interface BaseSharedExtendableModel extends SharedModelInterface
{
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    options: string | null;
    remember_token: string | null;
    created_at: string | null;
    updated_at: string | null;
    role: string | null;
    membership_level: string | null;
    phone: string | null;
    avatar: string | null;
    bio: string | null;
    settings: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
}
