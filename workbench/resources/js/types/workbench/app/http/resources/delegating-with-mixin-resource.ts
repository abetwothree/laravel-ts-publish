import type { DatabaseNotification } from '../../../../illuminate/notifications';
import type { MembershipLevelType, RoleType } from '../../enums';
import type { Address, Comment, Image, Order, Post, Profile, Team } from '../../models';

/**
 * Resource that delegates to parent with a known model — tests JsonResource base delegation.
 *
 * @see Workbench\App\Http\Resources\DelegatingWithMixinResource
 */
export interface DelegatingWithMixinResource
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
    profile: Profile | null;
    posts: Post[];
    comments: Comment[];
    orders: Order[];
    addresses: Address[];
    teams: Team[];
    ownedTeams: Team[];
    images: Image[];
    notifications: DatabaseNotification[];
}
