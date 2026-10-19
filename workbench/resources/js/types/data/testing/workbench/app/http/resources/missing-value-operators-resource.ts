import type { User } from '../../models';

/**
 * A MissingValue passed on through `??`, a `when()` default and a ternary keeps its key optional.
 *
 * @see Workbench\App\Http\Resources\MissingValueOperatorsResource
 */
export interface MissingValueOperatorsResource
{
    direct_coalesce?: User | string;
    when_wrap_default?: User | string;
    when_missing_default?: string | User;
    ternary?: User | string;
    nested_coalesce: { inner?: User | string };
    coalesce_right_missing: string | User;
}
