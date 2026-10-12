import type { Comment, Post } from '../../models';

/**
 * A tag is read whole, whatever its spacing: the wrapped `$resource`'s spaced `@var` still names its model, and a
 * helper's `@return` keeps a spaced `| null`, an array of collections and a union of spaced generics.
 *
 * @see Workbench\App\Http\Resources\SpacedDocblockResource
 */
export interface SpacedDocblockResource
{
    title: string;
    subtitle: string | null;
    pages: Comment[][];
    threads: Comment[] | Post[];
}
