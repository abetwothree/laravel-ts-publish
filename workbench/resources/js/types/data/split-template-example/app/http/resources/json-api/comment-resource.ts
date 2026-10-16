import type { JsonApiToOneRelationship } from '@tolki/types';

/**
 * A JSON:API resource with a to-one relationship of its own, which a nested include follows.
 *
 * @see Workbench\App\Http\Resources\JsonApi\CommentResource
 */
export interface CommentResource
{
    id: string;
    type: 'comments';
    attributes: { content: string; is_flagged: boolean };
    relationships?: { user?: JsonApiToOneRelationship<'users'> };
}
