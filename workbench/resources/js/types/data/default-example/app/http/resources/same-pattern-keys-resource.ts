/**
 * Interpolated keys whose pattern another spread method also fills, through a named key or its own
 * interpolated key, so each signature's value must cover every key it matches.
 *
 * @see Workbench\App\Http\Resources\SamePatternKeysResource
 */
export interface SamePatternKeysResource
{
    [key: `${string}_tag`]: string | number | undefined;
    price_tag: number;
    [key: `${string}_note`]: string | number | undefined;
    count_note: number;
    [key: `${string}_code`]: string | number | undefined;
    [key: `${string}_mark`]: string | number | undefined;
    id: number;
}
