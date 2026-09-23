import type { Comment } from '../../models';

/**
 * An inline `@var` types a local only where the engine's reading of the assigned value is vague: an arm it cannot
 * type, or a value it cannot read. A known reading stands whatever the tag says, a closure parameter reassigned under
 * a tag takes its new value, and a tag the package cannot fully read binds nothing.
 *
 * @see Workbench\App\Http\Resources\DeclaredPrecedenceResource
 */
export interface DeclaredPrecedenceResource
{
    picked: string | null;
    picked_strict: string;
    picked_or_zero: number | string;
    elvis: string | null;
    literal: { a: number };
    heading: string;
    title_as_totals: string;
    verifiable_email: string;
    callable: unknown;
    callable_shape: unknown;
    closure: unknown;
    intersection: unknown;
    length?: number;
    transformed_length?: number;
    author_name?: string;
    first_comment?: Comment | null;
}
