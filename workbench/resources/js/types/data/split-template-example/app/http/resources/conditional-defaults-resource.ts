import type { User } from '../../models';
import type { UserResource } from '.';

/**
 * Exercises the conditional family's default argument. An explicit default means the key is always
 * present, so the property is required; the default's own type unions into the emitted type when it
 * resolves, and the value arm's type stands alone when it does not.
 *
 * @see Workbench\App\Http\Resources\ConditionalDefaultsResource
 */
export interface ConditionalDefaultsResource
{
    not_null_no_default?: string;
    not_null_with_default: string | number;
    not_null_same_type_default: number;
    null_with_default: string | null;
    not_null_explicit_null_default: string | null;
    not_null_named_default: string | number;
    not_null_spread_default?: string;
    when_no_default?: string;
    when_with_default: string | number;
    has_with_default: string | number;
    loaded_with_default: User | null;
    counted_with_default: number | string;
    aggregated_no_default?: number;
    aggregated_with_default: number | string;
    pivot_loaded_no_default?: unknown;
    pivot_loaded_with_default: unknown;
    pivot_loaded_as_no_default?: unknown;
    pivot_loaded_as_with_default: unknown;
    unless_no_default?: string;
    unless_with_default: string | number;
    appended_no_default?: string;
    appended_with_default: string | number;
    exists_no_default?: boolean;
    exists_with_default: boolean | string;
    transform_no_default?: boolean;
    transform_with_default: boolean | number;
    transform_with_one_param_default: boolean | number;
    unless_user_resource?: UserResource;
    transform_user_resource?: UserResource;
    merge_unless_label?: string;
}
