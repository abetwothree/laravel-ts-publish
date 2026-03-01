import { DatabaseNotification } from './';

export interface User
{
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    options: string | null;
    remember_token: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface UserRelations
{
    notifications: DatabaseNotification;
}

export interface UserRelationCounts
{
    notifications_count: number;
}

export interface UserRelationExists
{
    notifications_exists: boolean;
}
