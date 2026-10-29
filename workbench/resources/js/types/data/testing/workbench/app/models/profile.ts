import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '.';

/** @see Workbench\App\Models\Profile */
export interface Profile
{
    id: number;
    user_id: number;
    bio: string | null;
    avatar_url: string | null;
    date_of_birth: string | null;
    website: string | null;
    phone_number: string | null;
    /** Write-only mutator, no get — but `normalized_phone` is a real column, so it still types the column */
    normalized_phone: string | null;
    social_links: { twitter?: string; github?: string; linkedin?: string; website?: string };
    settings: { notifications_enabled: boolean; theme: "light" | "dark"; language: string };
    menu_settings: MenuSettingsType | null;
    timezone: string;
    locale: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProfileMutators
{
    /** Old-style mutator for avatar URL capitalization */
    formatted_bio: string;
    /** Computed age from date_of_birth */
    age: number | null;
    /** Full display name combining user name and bio snippet */
    display_summary: string;
}

export interface ProfileRelations
{
    // Relations
    user: User;
    // Counts
    user_count: number;
    // Exists
    user_exists: boolean;
}

export interface ProfileAll extends Profile, ProfileMutators, ProfileRelations {}
