/**
 * Fixture: builds its response in a local, then merges keys into it inside an `if` and a loop, which keeps every key
 * the local held. The merged keys stay unpublished, the known gap of a returned variable the walk reads only partly.
 *
 * @see Workbench\App\Http\Resources\MergedPostResource
 */
export interface MergedPostResource
{
    id: number;
    title: string;
}
