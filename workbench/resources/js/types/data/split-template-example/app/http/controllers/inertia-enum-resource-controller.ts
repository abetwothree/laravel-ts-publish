import { defineRoute, annotatePageProps, type AsEnum } from '@tolki/ts';

import type { Status } from '../../enums';

export type IndexPageProps = Inertia.SharedData & { statuses: { data: AsEnum<typeof Status>[] } };

/**
 * Every status case inside the enum resource collection's wrap key.
 *
 * Result should be { statuses: { data: AsEnum<typeof Status>[] } }
 */
export const index = annotatePageProps<IndexPageProps>()(defineRoute({
    name: 'enum-resources.statuses',
    url: '/enum-resources/statuses',
    methods: ['get', 'head'] as const,
    component: 'Enums/Statuses',
}));

/**
 * Renders an EnumResource prop, which Inertia sends as the resource's objects rather than the bare cases.
 *
 * @see Workbench\App\Http\Controllers\InertiaEnumResourceController
 */
const InertiaEnumResourceController = {
    index,
};

export default InertiaEnumResourceController;
