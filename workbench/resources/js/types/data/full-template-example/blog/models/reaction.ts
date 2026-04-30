import type { User } from '../../app/models';
import type { Article } from '.';

/**
 * @see Workbench\Blog\Models\Reaction
 */
export interface Reaction
{
    // Columns
    id: number;
    article_id: number;
    user_id: number;
    emoji: string;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    article: Article;
    user: User;
    // Counts
    article_count: number;
    user_count: number;
    // Exists
    article_exists: boolean;
    user_exists: boolean;
}
