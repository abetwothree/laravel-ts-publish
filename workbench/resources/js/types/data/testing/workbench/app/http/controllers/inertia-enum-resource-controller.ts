import { defineRoute, annotatePageProps, type AsEnum } from '@tolki/ts';

import type { Status as CrmStatus } from '../../../crm/enums';
import type { Role, Status as WorkbenchStatus, StatusType, Visibility } from '../../enums';

export type IndexPageProps = Inertia.SharedData & { statuses: { data: AsEnum<typeof WorkbenchStatus>[] } };

/**
 * Every status case inside the enum resource collection's wrap key.
 *
 * Result should be { statuses: { data: AsEnum<typeof Status>[] } }
 */
export const index = /* @__PURE__ */ annotatePageProps<IndexPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'enum-resources.statuses',
    url: '/enum-resources/statuses',
    methods: ['get', 'head'] as const,
    component: 'Enums/Statuses',
}));

export type UnionsPageProps = Inertia.SharedData & { either: AsEnum<typeof Role> | AsEnum<typeof WorkbenchStatus>, mixed: AsEnum<typeof WorkbenchStatus> | StatusType };

/**
 * A ternary over two enums' resources, and one of a resource and a bare case of its enum.
 *
 * Result should be { either: AsEnum<typeof Role> | AsEnum<typeof Status>,
 * mixed: AsEnum<typeof Status> | StatusType }
 */
export const unions = /* @__PURE__ */ annotatePageProps<UnionsPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'enum-resources.unions',
    url: '/enum-resources/unions',
    methods: ['get', 'head'] as const,
    component: 'Enums/Unions',
}));

export type VisibilityPageProps = Inertia.SharedData & { visibility: AsEnum<typeof Visibility> | never[] };

/**
 * The post's nullable visibility, which Inertia sends as `[]` while it is null.
 *
 * Result should be { visibility: AsEnum<typeof Visibility> | never[] }
 */
export const visibility = /* @__PURE__ */ annotatePageProps<VisibilityPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'enum-resources.visibility',
    url: '/enum-resources/{post}/visibility',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Enums/Visibility',
}));

export type MixesPageProps = Inertia.SharedData & { wrap_or_other_case: AsEnum<typeof Role> | StatusType, wrap_or_nested_nullable_case: AsEnum<typeof WorkbenchStatus> | StatusType | null, three_wraps: AsEnum<typeof Role> | AsEnum<typeof WorkbenchStatus> | AsEnum<typeof Visibility>, same_named_wraps: AsEnum<typeof WorkbenchStatus> | AsEnum<typeof CrmStatus>, collection_or_case: { data: AsEnum<typeof WorkbenchStatus>[] } | StatusType, nested_two_wraps: { x: AsEnum<typeof Visibility> | never[] | AsEnum<typeof WorkbenchStatus> } };

/**
 * Unions of EnumResource props with a bare case, a nested ternary, a third wrap, a same-named enum and a collection
 * (GH-108, GH-160). Each wrap arm sends its enum's object, each bare case its value.
 */
export const mixes = /* @__PURE__ */ annotatePageProps<MixesPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'enum-resources.mixes',
    url: '/enum-resources/{post}/mixes',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Enums/Mixes',
}));

/**
 * Renders an EnumResource prop, which Inertia sends as the resource's objects rather than the bare cases.
 *
 * @see Workbench\App\Http\Controllers\InertiaEnumResourceController
 */
const InertiaEnumResourceController = {
    index,
    unions,
    visibility,
    mixes,
};

export default InertiaEnumResourceController;
