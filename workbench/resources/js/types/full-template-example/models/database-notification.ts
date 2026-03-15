export interface DatabaseNotification
{
    // Columns
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: unknown[];
    read_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** Get the notifiable entity that the notification belongs to. */
    notifiable: DatabaseNotification;
    // Counts
    notifiable_count: number;
    // Exists
    notifiable_exists: boolean;
}
