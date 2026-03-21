/** Exercises: multiple whenHas on different column types, multiple whenNotNull. */
export interface ProfileResource
{
    id: number;
    bio: string | null;
    avatar_url: string | null;
    date_of_birth?: string;
    website?: string | null;
    phone_number?: string | null;
    social_links?: { twitter?: string; github?: string; linkedin?: string; website?: string };
    timezone?: string;
    locale?: string;
}
