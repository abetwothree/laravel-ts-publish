import type { Post } from '../../../models';

/**
 * An outer declaration over a value, an inline `@var`, a `toArray()` `@return` key or a closure `@return`: it replaces
 * each arm only a declaration produced, and keeps each arm the body proves (GH-134; plan task T5).
 *
 * `featured_image_url` is a nullable string column, `title` a NOT NULL one, and a comment's `parent_id` a nullable key.
 *
 * @see Workbench\App\Http\Resources\DocblockReads\DeclaredArmsResource
 */
export interface DeclaredArmsResource
{
    var_closure_union: Post[];
    var_closure_nullable: Post[];
    var_closure_model: Post[];
    var_closure_model_narrowed: Post[];
    var_closure_filtered: Post[];
    var_two_array_nullsafe: string[];
    var_param_nullsafe: string[];
    var_nullable_return: string[];
    var_hedged_map: string[];
    var_first_kid_name: string;
    var_parent_ids: number[];
    var_column: string;
    var_mapped_column: string[];
    var_zipped: { content: string; other: number }[];
    var_flags: true[];
    decl_closure_union: Post[];
    decl_closure_nullable: Post[];
    decl_closure_array: { a: number }[];
    decl_two_array_nullsafe: string[];
    decl_param_nullsafe: string[];
    decl_nullable_return: string[];
    decl_hedged_map: string[];
    decl_first_kid_name: string;
    decl_column: string;
    when_closure_union?: Post[];
    when_closure_array?: { a: number }[];
    when_column?: string;
    when_first_kid_name?: string;
    when_flags?: true[];
    bare_closure_union: (Post | null)[];
    bare_param_nullsafe: (string | null)[];
    bare_hedged_map: (string | null)[] | Record<string, string | null>;
    bare_first_kid_name: string | null;
    bare_column: string | null;
    bare_zipped: { content: string; other: unknown }[];
    var_ternary: string | null;
    var_coalesce_null: string | null;
    var_coalesced: string;
    var_uncovered: { a: number; b: string | null };
    var_mixed: string | null;
    var_nullable_column: string | null;
    var_proven_nullsafe: string | null;
    var_mapped_coalesce_null: (string | null)[];
    decl_ternary: string | null;
    decl_coalesce_null: string | null;
    decl_nullable_column: string | null;
    when_ternary?: string | null;
    when_nullable_column?: string | null;
}
