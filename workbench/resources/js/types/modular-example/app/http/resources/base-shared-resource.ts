import type { SharedInterface } from '@/types/shared';

/** Parent resource that uses SharedExtendsInterface — tests BFS dedup when child also uses the same trait. */
export interface BaseSharedResource extends SharedInterface
{
}
