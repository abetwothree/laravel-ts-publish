/**
 * Fixture: a runtime class of BaseTagBoardResource's `toArray()` that appends to an object, appends beside a key
 * write, and keys an object by the tag passed.
 *
 * @see Workbench\App\Http\Resources\TagBoardResource
 */
export interface TagBoardResource
{
    labels: { a: string } & Record<number, string>;
    both: string[] | ({ k?: number } & Record<number, string>);
    dynamic: { a: number; [key: string]: number };
}
