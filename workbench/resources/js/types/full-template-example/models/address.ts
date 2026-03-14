import type { User } from './';

export interface Address
{
    // Columns
    id: number;
    user_id: number;
    label: string | null;
    line_1: string;
    line_2: string | null;
    city: string;
    state: string | null;
    postal_code: string;
    country_code: string;
    latitude: number | null;
    longitude: number | null;
    is_default: boolean;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    full_address: string | null;
    /** Whether coordinates are available */
    has_coordinates: boolean;
    // Relations
    user: User;
    // Counts
    user_count: number;
    // Exists
    user_exists: boolean;
}
