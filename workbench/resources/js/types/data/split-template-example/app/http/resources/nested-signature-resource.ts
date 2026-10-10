/**
 * Index signatures inside nested shapes: one beside a named key its pattern matches, one whose text holds a backslash.
 *
 * @see Workbench\App\Http\Resources\NestedSignatureResource
 */
export interface NestedSignatureResource
{
    id: number;
    box: { [key: `${string}_tag`]: string | number | undefined; price_tag: number };
    units: { [key: `${string}\\unit`]: string | undefined };
}
