import type { Comment } from '../../models';

/**
 * Exercises collection pipelines that must keep their element type to the end of the chain:
 * a trailing values()->all(), concat() of the same relation, a chain rooted at collect(),
 * and data_get() standing in for a nullsafe property chain.
 *
 * @see Workbench\App\Http\Resources\CollectionPipelineResource
 */
export interface CollectionPipelineResource
{
    comment_ids: number[];
    title_words: { word: string }[];
    author_name: string | null;
    author_name_or_guest: string | null;
    doubled: Comment[];
    typed?: { id: number }[];
}
