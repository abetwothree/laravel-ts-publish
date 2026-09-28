import type { StatusType } from '../../enums';
import type { Comment } from '../../models';

/**
 * An inline `@var` types a local only where the engine's reading of the assigned value is vague: a value it cannot
 * read, or a lone `null` left once an arm it cannot type dropped. Any other reading stands, a closure parameter
 * reassigned under a tag takes its new value, and a tag with a form the package cannot read binds nothing.
 *
 * @see Workbench\App\Http\Resources\DeclaredPrecedenceResource
 */
export interface DeclaredPrecedenceResource
{
    picked: string | null;
    picked_strict: string;
    picked_or_zero: number;
    elvis: string | null;
    literal: { a: number };
    heading: string;
    title_as_totals: string;
    verifiable_email: string;
    callable: unknown;
    callable_shape: unknown;
    closure: unknown;
    intersection: unknown;
    tuple: unknown;
    decoded: unknown;
    literal_keys: unknown;
    quoted_key: unknown;
    kept_title: string;
    kept_record: { a: number; b: string };
    kept_shape: { a: number; b: string };
    kept_status: StatusType;
    length?: number;
    transformed_length?: number;
    author_name?: string;
    first_comment?: Comment | null;
}
