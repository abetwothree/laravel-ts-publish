import type { User } from '../../app/models';

/**
 * @see Illuminate\Notifications\DatabaseNotification
 */
export interface DatabaseNotification
{
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: unknown[];
    read_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface DatabaseNotificationRelations
{
    // Relations
    /** Get the notifiable entity that the notification belongs to. */
    notifiable: User;
    // Counts
    notifiable_count: number;
    // Exists
    notifiable_exists: boolean;
}

export interface DatabaseNotificationAll extends DatabaseNotification, DatabaseNotificationRelations {}
