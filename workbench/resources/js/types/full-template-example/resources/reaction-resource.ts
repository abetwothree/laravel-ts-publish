import type { Article, User } from '../models';

/** Exercises: multiple whenLoaded bare — both same-module (Article) and cross-module (App\User) model type resolution. */
export interface ReactionResource
{
    id: number;
    emoji: string;
    article?: Article;
    user?: User;
}
