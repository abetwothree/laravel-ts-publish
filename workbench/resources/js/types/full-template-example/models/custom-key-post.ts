export interface CustomKeyPost
{
    // Columns
    id: number;
    title: string;
    content: string;
    user_id: number;
    status: number;
    published_at: string | null;
    metadata: unknown | null;
    rating: number | null;
    category: string;
    options: string | null;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    category_id: number | null;
    visibility: string | null;
    priority: number | null;
    word_count: number | null;
    reading_time_minutes: number | null;
    featured_image_url: string | null;
    is_pinned: number;
}
