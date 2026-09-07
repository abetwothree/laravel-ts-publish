/**
 * Ternary and Elvis arms that are each nullable: the union must carry one trailing null.
 *
 * @see Workbench\App\Http\Resources\ImageNullableArmsResource
 */
export interface ImageNullableArmsResource
{
    id: number;
    size: number | string | null;
    label: string | number | null;
}
