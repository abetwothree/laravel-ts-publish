/**
 * Optional casts on two index signatures, one on the class and one on toArray(): neither can carry `?:`.
 *
 * @see Workbench\App\Http\Resources\OptionalSignatureCastResource
 */
export interface OptionalSignatureCastResource
{
    id: number;
    [key: `${string}_tag`]: string | undefined;
    [key: `${string}_note`]: number | undefined;
}
