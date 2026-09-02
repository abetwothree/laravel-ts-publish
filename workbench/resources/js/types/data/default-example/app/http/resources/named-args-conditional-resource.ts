import type { Comment } from '../../models';

/**
 * The conditional family called with named arguments. PHP binds each argument to its parameter by name
 * and Laravel then tests func_num_args(), so a named `default:` is a real default however it is written.
 *
 * @see Workbench\App\Http\Resources\NamedArgsConditionalResource
 */
export interface NamedArgsConditionalResource
{
    not_null_named_default: string | number;
    when_all_named: string | number;
    loaded_named_default: Comment[];
    counted_named_out_of_order: number;
}
