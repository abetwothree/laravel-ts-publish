/**
 * Bindings that hold inside one block: a local assigned in an `if` or a `try` block, read after it there; an early-exit
 * `instanceof` guard nested in a block, for the rest of that block; and a positive `instanceof` test, in its block.
 *
 * `imageable` is a morphTo over int-keyed Post and User and string-keyed Product, so a narrowed key read loses the
 * string arm.
 *
 * @see Workbench\App\Http\Resources\BlockScopedBindingsResource
 */
export interface BlockScopedBindingsResource
{
    label?: string;
    bytes?: number;
    loop_post_key?: number;
    guarded_post_key?: number;
    positive_title?: string;
    positive_email?: string;
    loaded_post_key?: { k: number } | null;
}
