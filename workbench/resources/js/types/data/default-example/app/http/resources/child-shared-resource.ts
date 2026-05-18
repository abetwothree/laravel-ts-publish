import type { SharedInterface } from '@/types/shared';

/**
 * Child resource that uses SharedExtendsInterface AND extends a parent that also uses it. SharedExtendsInterface should appear only once in the result despite being reachable via two paths.
 *
 * @see Workbench\App\Http\Resources\ChildSharedResource
 */
export interface ChildSharedResource extends SharedInterface
{
}
