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
    notifiable: DatabaseNotification;
}

export interface DatabaseNotificationRelationCounts
{
    notifiable_count: number;
}

export interface DatabaseNotificationRelationExists
{
    notifiable_exists: boolean;
}
