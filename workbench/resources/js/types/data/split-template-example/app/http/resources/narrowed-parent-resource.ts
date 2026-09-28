/**
 * Narrowing fixture: `attachable` is a morphTo, so `$parent` holds a union until an early-return
 * `instanceof` guard proves it a Post. `$record`'s ternary narrows the same way in one expression.
 *
 * @see Workbench\App\Http\Resources\NarrowedParentResource
 */
export interface NarrowedParentResource
{
    parent?: { title: string; class: string; morph: string } | null;
    record_title: string | null;
}
