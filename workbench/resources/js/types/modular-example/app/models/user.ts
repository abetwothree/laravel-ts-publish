import { type AsEnum } from '@tolki/enum';

import type { DatabaseNotification } from '../../illuminate/notifications';
import type { MembershipLevel, MembershipLevelType, Role, RoleType } from '../enums';
import type { Address, Comment, Image, Order, Post, Profile, Team } from '.';

/** Application user account */
export interface User
{
    id: number;
    /** User name formatted with first letter capitalized */
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
}

export interface UserResource extends Omit<User, 'role' | 'membership_level'>
{
    role: AsEnum<typeof Role> | null;
    membership_level: AsEnum<typeof MembershipLevel> | null;
}

export interface UserMutators
{
    /** User initials (e.g. "JD" for "John Doe") */
    initials: string;
    /** Whether the user is a premium member */
    is_premium: boolean;
}

export interface UserRelations
{
    // Relations
    profile: Profile;
    posts: Post[];
    comments: Comment[];
    orders: Order[];
    addresses: Address[];
    teams: Team[];
    owned_teams: Team[];
    /** Polymorphic images (avatar gallery, etc.) */
    images: Image[];
    /** Get the entity's notifications. */
    notifications: DatabaseNotification[];
    // Counts
    profile_count: number;
    posts_count: number;
    comments_count: number;
    orders_count: number;
    addresses_count: number;
    teams_count: number;
    owned_teams_count: number;
    images_count: number;
    notifications_count: number;
    // Exists
    profile_exists: boolean;
    posts_exists: boolean;
    comments_exists: boolean;
    orders_exists: boolean;
    addresses_exists: boolean;
    teams_exists: boolean;
    owned_teams_exists: boolean;
    images_exists: boolean;
    notifications_exists: boolean;
}

export interface UserAll extends User, UserMutators, UserRelations {}

export interface UserAllResource extends UserResource, UserMutators, UserRelations {}
