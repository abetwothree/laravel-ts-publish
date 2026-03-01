export interface Comment
{
    id: number;
    content: string;
    post_id: number;
    user_id: number;
    is_flagged: number;
    flagged_at: string | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
    updated_at: string | null;
}
