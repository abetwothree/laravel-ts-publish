import type { MembershipLevelType, RoleType } from '../enums';

/** Resource spreading parent::toArray() from JsonResource base with extra keys. */
export interface SpreadJsonBaseResource
{
    id: number;
    name: string;
    email: string;
    email_verified_at: string;
    password: string;
    options: Record<string, unknown> | null;
    remember_token: string | null;
    created_at: string;
    updated_at: string;
    role: RoleType;
    membership_level: MembershipLevelType;
    phone: string | null;
    avatar: string | null;
    bio: string | null;
    settings: { theme: "light" | "dark"; notifications: boolean; locale: string } | null;
    last_login_at: string;
    last_login_ip: string | null;
    initials: unknown;
    is_premium: unknown;
    full_name: unknown;
}
