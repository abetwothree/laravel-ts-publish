/**
 * Its returned array names `state` twice. PHP keeps the last value in the first position, so the method's cast
 * must retype the entry that publishes, not only the first.
 *
 * @see Workbench\App\Http\Resources\DuplicateKeyCastResource
 */
export interface DuplicateKeyCastResource
{
    state: 'draft' | 'published';
    title: string;
}
