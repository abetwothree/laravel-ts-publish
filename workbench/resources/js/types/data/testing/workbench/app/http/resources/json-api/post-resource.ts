import type { JsonApiToManyRelationship, JsonApiToOneRelationship } from '@tolki/types';

/**
 * A body-less JSON:API resource: its attributes and relationships named by property.
 *
 * @see Workbench\App\Http\Resources\JsonApi\PostResource
 */
export interface PostResource
{
    id: string;
    type: 'posts';
    attributes: { title: string; content: string; word_count: number | null; published_at: string | null };
    relationships?: { author?: JsonApiToOneRelationship<'users'>; comments?: JsonApiToManyRelationship<'comments'>; categoryRel?: JsonApiToOneRelationship<'categories'> };
}
