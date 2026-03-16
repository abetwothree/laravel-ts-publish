export interface StrictCompositeComment
{
    // Columns
    id: number;
    body: string;
    commentable_type: string;
    commentable_id_1: number;
    commentable_id_2: number;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    commentable: StrictCompositeComment | null;
    // Counts
    commentable_count: number;
    // Exists
    commentable_exists: boolean;
}
