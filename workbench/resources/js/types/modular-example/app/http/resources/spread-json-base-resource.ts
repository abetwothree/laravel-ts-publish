import type { MembershipLevelType, RoleType } from '../../enums';

/** Resource spreading parent::toArray() from JsonResource base with extra keys. */
export interface SpreadJsonBaseResource
{
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    options: Record<string, unknown> | null;
    remember_token: string | null;
    created_at: string | null;
    updated_at: string | null;
    role: RoleType | null;
    membership_level: MembershipLevelType | null;
    phone: string | null;
    avatar: string | null;
    bio: string | null;
    settings: { theme: "light" | "dark"; notifications: boolean; locale: string } | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    initials: string;
    is_premium: boolean;
    full_name: unknown;
}
