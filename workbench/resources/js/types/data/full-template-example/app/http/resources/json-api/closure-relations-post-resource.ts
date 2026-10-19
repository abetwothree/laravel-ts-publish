import type { JsonApiToManyRelationship, JsonApiToOneRelationship } from '@tolki/types';

/**
 * A JSON:API resource whose toAttributes() mixes attribute names with a closure Laravel calls, and whose
 * toRelationships() resolves each relationship through a closure.
 *
 * @see Workbench\App\Http\Resources\JsonApi\ClosureRelationsPostResource
 */
export interface ClosureRelationsPostResource
{
    id: string;
    type: 'closure_relations_posts';
    attributes: { title: string; published_at: string | null; excerpt: string | null; is_published: boolean };
    relationships?: { author?: JsonApiToOneRelationship<'users'>; comments?: JsonApiToManyRelationship<'comments'> };
}
