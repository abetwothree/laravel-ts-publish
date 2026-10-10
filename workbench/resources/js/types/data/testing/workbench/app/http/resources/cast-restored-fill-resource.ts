/**
 * A docblock-filled signature beside a same-pattern key only the class-level cast types, so the fill joins the cast.
 *
 * @see Workbench\App\Http\Resources\CastRestoredFillResource
 */
export interface CastRestoredFillResource
{
    [key: `${string}_tag`]: string | number | undefined;
    main_tag: number;
}
