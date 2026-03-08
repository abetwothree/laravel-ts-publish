import { MembershipLevelType, RoleType } from '../enums';
import { Address, Comment, DatabaseNotification, Image, Order, Post, Profile, Team } from './';

export interface User
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
}

export interface UserMutators
{
    initials: string;
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
    images: Image[];
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
