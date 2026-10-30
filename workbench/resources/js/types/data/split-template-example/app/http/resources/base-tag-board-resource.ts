/**
 * Fixture: a model-less resource whose `toArray()` reads untyped array defaults that TagBoardResource writes by
 * element.
 *
 * @see Workbench\App\Http\Resources\BaseTagBoardResource
 */
export interface BaseTagBoardResource
{
    labels: { a: string };
    both: string[];
    dynamic: { a: number };
}
