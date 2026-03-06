export interface DatabaseNotification
{
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: Array<unknown>;
    read_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface DatabaseNotificationRelations
{
    // Relations
    notifiable: DatabaseNotification;
    // Counts
    notifiable_count: number;
    // Exists
    notifiable_exists: boolean;
}

export interface DatabaseNotificationAll extends DatabaseNotification, DatabaseNotificationRelations {}
