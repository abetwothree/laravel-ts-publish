/**
 * Narrowing fixture: `imageable` is a morphTo over int-keyed Post, User and CRM User and string-keyed Product.
 * A ternary whose `instanceof` test (or `||` chain of them) reads the same expression as its true arm narrows
 * that arm to the tested classes, so a key read through the bound variable loses the string arm.
 *
 * @see Workbench\App\Http\Resources\NarrowedImageableResource
 */
export interface NarrowedImageableResource
{
    either_id: number | null;
    single_id: number | null;
    open_id: number | string | null;
    either_title: string | null;
}
