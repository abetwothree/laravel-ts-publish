import type { SharedInterface } from '@/types/shared';

/**
 * Parent resource that uses SharedExtendsInterface — tests BFS dedup when child also uses the same trait.
 *
 * @see Workbench\App\Http\Resources\BaseSharedResource
 */
export interface BaseSharedResource extends SharedInterface
{
}
