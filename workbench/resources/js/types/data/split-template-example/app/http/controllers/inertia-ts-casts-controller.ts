import { defineRoute, annotatePageProps } from '@tolki/ts';
import type { PageMeta } from '@workbench/types';

export type IndexPageProps = Inertia.SharedData & { count: string, meta: PageMeta };

/**
  * Demonstrates TsCasts overrides on an Inertia route action. The `count` prop is auto-detected as `number` by Surveyor; TsCasts overrides it to `string` to verify the override mechanism works. The `meta` prop is not in the Surveyor data, so TsCasts adds it with an import from a custom package.
  */
export const index = annotatePageProps<IndexPageProps>()(defineRoute({
    name: 'ts-casts.index',
    url: '/ts-casts',
    methods: ['get'] as const,
    component: 'TsCasts/Index',
}));

/**
 * @see Workbench\App\Http\Controllers\InertiaTsCastsController
 */
const InertiaTsCastsController = {
    index,
};

export default InertiaTsCastsController;
