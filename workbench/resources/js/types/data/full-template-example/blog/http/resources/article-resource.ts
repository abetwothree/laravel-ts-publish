import { type AsEnum } from '@tolki/ts';

import { ArticleStatus, ContentType } from '../../enums';
import type { User } from '../../../app/models';
import type { ReactionResource } from '.';

/**
 * Exercises: multiple EnumResource::make, when(cond, Resource::collection),
 * whenLoaded bare (cross-module App\User as author), whenNotNull, whenCounted,
 * whenAggregated, when conditional with direct property.
 *
 * @see Workbench\Blog\Http\Resources\ArticleResource
 */
export type ArticleResource = {
    id: number;
    title: string;
    slug: string;
    excerpt?: string;
    body: string;
    status: AsEnum<typeof ArticleStatus>;
    content_type: AsEnum<typeof ContentType>;
    meta_description?: string;
    published_at?: string;
    author?: User;
    reactions?: ReactionResource[];
    reactions_count?: number;
    reactions_avg?: number;
} & (
    | { is_featured: true; featured_image: string | null }
    | { is_featured: false; featured_image?: never }
);
