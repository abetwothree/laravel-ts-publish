import type { Comment } from '../../models';

/**
 * Reads a single-model accessor inside an inline member and under a key that differs from the
 * accessor name, so neither the top-level fallback nor the inline import gatherer rescues it.
 *
 * @see Workbench\App\Http\Resources\PostSpotlightResource
 */
export interface PostSpotlightResource
{
    id: number;
    headline: Comment | null;
    spotlight: { comment: Comment | null };
}
