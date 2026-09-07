import type { DatabaseNotification } from '../../../../illuminate/notifications';
import type { MembershipLevelType, RoleType } from '../../enums';
import type { Address, Comment, Image, Order, Post, Profile, Team } from '../../models';

/**
 * Exercises return $this->except([...]) against a model with $hidden columns.
 *
 * The property set is derived from every model attribute minus the named keys, so it is
 * implicit — exclude_hidden must drop $hidden columns here even though none are named.
 *
 * @see Workbench\App\Http\Resources\UserExceptResource
 */
export interface UserExceptResource
{
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
    primaryAddress: Address | null;
    teams: Team[];
    ownedTeams: Team[];
    images: Image[];
    notifications: DatabaseNotification[];
}
