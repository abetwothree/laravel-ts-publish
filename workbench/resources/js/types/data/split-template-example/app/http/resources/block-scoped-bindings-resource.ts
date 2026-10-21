/**
 * Bindings that hold inside one block: a local assigned in an `if` or a `try` block, read after it there.
 *
 * @see Workbench\App\Http\Resources\BlockScopedBindingsResource
 */
export interface BlockScopedBindingsResource
{
    label?: string;
    bytes?: number;
}
