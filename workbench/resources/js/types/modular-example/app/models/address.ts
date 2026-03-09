import { User } from '.';

export interface Address
{
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
}

export interface AddressMutators
{
    full_address: string | null;
    has_coordinates: boolean;
}

export interface AddressRelations
{
    // Relations
    user: User;
    // Counts
    user_count: number;
    // Exists
    user_exists: boolean;
}

export interface AddressAll extends Address, AddressMutators, AddressRelations {}
