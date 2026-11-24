import type { Comment } from '../../../models';

/**
 * `@var X[]` over string-keyed locals with controls, and a typed two-array `array_map()` (GH-134; plan tasks T1, T4).
 *
 * @see Workbench\App\Http\Resources\DocblockReads\BracketVarResource
 */
export interface BracketVarResource
{
    comments_by_slug: Comment[];
    comments_by_slug_nested: { inner: Comment[] };
    comment_contents_by_slug_mapped: string[];
    comment_contents_by_slug_collected: string[];
    comment_record: Record<string, Comment>;
    comment_list: Comment[];
    options_by_name: { itemId: number }[];
    options_by_name_nested: { inner: { itemId: number }[] };
    option_ids_by_name_mapped: number[];
    option_ids_by_name_collected: number[];
    option_record: Record<string, { itemId: number }>;
    option_list: { itemId: number }[];
    zipped_with_untyped_posts: { content: string; title: string }[];
    titles_from_untyped_posts: string[] | Record<string, string>;
    zipped_typed_comments: { content: string; id: number }[];
}
