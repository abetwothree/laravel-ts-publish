import type { User } from '../../app/models';
import type { Article } from '.';

export interface Reaction
{
    id: number;
    article_id: number;
    user_id: number;
    emoji: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ReactionRelations
{
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

export interface ReactionAll extends Reaction, ReactionRelations {}
