import type { MembershipLevelType, RoleType } from '../enums';
import type { Address, Comment, DatabaseNotification, Image, Order, Post, Profile, Team } from '../models';

/** Exercises the bug where findBestArrayReturn() selects a nested closure's return array (more items) over the actual toArray() return (fewer items due to ...parent::toArray() spread counting as one). The outer toArray() return has 2 items: ...parent::toArray() + 'metadata'. The closure inside whenLoaded has 4 items, so the old recursive finder would pick the closure's array, flattening it as top-level properties and losing the parent spread + metadata key entirely. */
export interface SpreadWithClosureResource
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
    metadata?: { profile_bio: string | null; profile_avatar: unknown; profile_theme: unknown; profile_locale: string };
}
