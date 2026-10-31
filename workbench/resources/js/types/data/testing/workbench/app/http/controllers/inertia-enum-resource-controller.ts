import { defineRoute, annotatePageProps, type AsEnum } from '@tolki/ts';

import type { Role, Status, StatusType } from '../../enums';

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

export type UnionsPageProps = Inertia.SharedData & { either: AsEnum<typeof Role> | AsEnum<typeof Status>, mixed: AsEnum<typeof Status> | StatusType };

/**
 * A ternary over two enums' resources, and one of a resource and a bare case of its enum.
 *
 * Result should be { either: AsEnum<typeof Role> | AsEnum<typeof Status>,
 * mixed: AsEnum<typeof Status> | StatusType }
 */
export const unions = annotatePageProps<UnionsPageProps>()(defineRoute({
    name: 'enum-resources.unions',
    url: '/enum-resources/unions',
    methods: ['get', 'head'] as const,
    component: 'Enums/Unions',
}));

/**
 * Renders an EnumResource prop, which Inertia sends as the resource's objects rather than the bare cases.
 *
 * @see Workbench\App\Http\Controllers\InertiaEnumResourceController
 */
const InertiaEnumResourceController = {
    index,
    unions,
};

export default InertiaEnumResourceController;
