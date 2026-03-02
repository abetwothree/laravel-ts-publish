import { User } from './';

export interface Profile
{
    id: number;
    user_id: number;
    bio: string | null;
    avatar_url: string | null;
    date_of_birth: string | null;
    website: string | null;
    phone_number: string | null;
    social_links: { twitter?: string; github?: string; linkedin?: string; website?: string };
    settings: { notifications_enabled: boolean; theme: "light" | "dark"; language: string };
    timezone: string;
    locale: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProfileMutators
{
    age: number | null;
    display_summary: string;
}

export interface ProfileRelations
{
    user: User;
}

export interface ProfileRelationCounts
{
    user_count: number;
}

export interface ProfileRelationExists
{
    user_exists: boolean;
}
