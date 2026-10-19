import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '../../models';

/**
 * Declares no toArray(), so it publishes what Profile's toArray() writes: every column, `menu_settings` through its
 * cast class's #[TsType] import, no accessor Profile does not append, and its `user` relation only when loaded.
 *
 * @see Workbench\App\Http\Resources\ProfileSummaryResource
 */
export interface ProfileSummaryResource
{
    id: number;
    user_id: number;
    bio: string | null;
    avatar_url: string | null;
    date_of_birth: string | null;
    website: string | null;
    phone_number: string | null;
    normalized_phone: string | null;
    social_links: { twitter?: string; github?: string; linkedin?: string; website?: string };
    settings: { notifications_enabled: boolean; theme: "light" | "dark"; language: string };
    menu_settings: MenuSettingsType | null;
    timezone: string;
    locale: string;
    created_at: string | null;
    updated_at: string | null;
    user?: User;
}
