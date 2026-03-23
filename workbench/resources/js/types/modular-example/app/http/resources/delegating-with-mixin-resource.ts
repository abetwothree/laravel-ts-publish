import type { MembershipLevelType, RoleType } from '../../enums';

/** Resource that delegates to parent with a known model — tests JsonResource base delegation. */
export interface DelegatingWithMixinResource
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
    initials: string;
    is_premium: boolean;
}
