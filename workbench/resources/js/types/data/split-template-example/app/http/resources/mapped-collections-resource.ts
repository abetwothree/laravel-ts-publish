import type { Comment } from '../../models';
import type { PostResource } from '.';

/**
 * Maps a post's comments through receivers other than `$this->comments`: a `collect()` root, a local holding the
 * relation, `getRelation()`, a key-breaking step, a query and a helper whose body maps them.
 *
 * @see Workbench\App\Http\Resources\MappedCollectionsResource
 */
export interface MappedCollectionsResource
{
    collected_spread: (Omit<Comment, 'flag'> & { flag: boolean })[];
    collected_reads: ({ content: string; who: string | null })[];
    collected_spread_in_author?: (Omit<Comment, 'flag'> & { flag: boolean })[];
    local_rows: ({ who: string | null })[];
    loaded_rows: (Pick<Comment, 'id' | 'content'>)[];
    local_proxy: { id: number; content: string }[];
    reassigned_parameter: string[];
    keyed_rows: { id: number }[] | Record<string, { id: number }>;
    sorted_ids: number[] | Record<string, number>;
    sorted_listed_ids: number[];
    sorted_first: string | null;
    loaded_values: { id: number }[];
    mapped_values: { id: number }[];
    query_rows: { id: number }[];
    declared_first: string | null;
    all_posts: PostResource[];
    post_rows: { id: number }[];
    query_service_rows: { id: number }[];
    unflagged_rows: { id: number }[];
}
