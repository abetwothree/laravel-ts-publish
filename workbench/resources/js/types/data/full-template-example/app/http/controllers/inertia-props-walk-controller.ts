import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { WhenKeySpreadResource } from '../resources/spreads';

export type BlockBuiltPageProps = Inertia.SharedData & { heading?: string };

/** A props local built and rendered inside one `if`. */
export const blockBuilt = /* @__PURE__ */ annotatePageProps<BlockBuiltPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'props-walk.block-built',
    url: '/props-walk/block-built',
    methods: ['get', 'head'] as const,
    component: 'PropsWalk/BlockBuilt',
}));

export type ExitingKeyWritePageProps = Inertia.SharedData & { heading: string };

/** A top-level props local given a key in a block that renders it and exits. */
export const exitingKeyWrite = /* @__PURE__ */ annotatePageProps<ExitingKeyWritePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'props-walk.exiting-key-write',
    url: '/props-walk/exiting-key-write',
    methods: ['get', 'head'] as const,
    component: 'PropsWalk/ExitingKeyWrite',
}));

export type ExitingNestedWholePageProps = Inertia.SharedData & { heading: string };

/** A whole write nested in a block that renders the local and exits. */
export const exitingNestedWhole = /* @__PURE__ */ annotatePageProps<ExitingNestedWholePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'props-walk.exiting-nested-whole',
    url: '/props-walk/exiting-nested-whole',
    methods: ['get', 'head'] as const,
    component: 'PropsWalk/ExitingNestedWhole',
}));

export type CoalescedSelfReadPageProps = Inertia.SharedData & { heading: string, total?: number | string };

/** A key a branch may skip, given a default by reading itself with `??`. */
export const coalescedSelfRead = /* @__PURE__ */ annotatePageProps<CoalescedSelfReadPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'props-walk.coalesced-self-read',
    url: '/props-walk/coalesced-self-read',
    methods: ['get', 'head'] as const,
    component: 'PropsWalk/CoalescedSelfRead',
}));

export type PlusOperatorPageProps = Inertia.SharedData & { heading: string, total: number | string };

/** A `+` of a default over a key a branch may skip. */
export const plusOperator = /* @__PURE__ */ annotatePageProps<PlusOperatorPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'props-walk.plus-operator',
    url: '/props-walk/plus-operator',
    methods: ['get', 'head'] as const,
    component: 'PropsWalk/PlusOperator',
}));

export type ResolvedWhenSpreadPageProps = Inertia.SharedData & { credit: WhenKeySpreadResource, credit_over_key: Omit<WhenKeySpreadResource, 'by'> & { by: number | string } };

/** A resource's resolve() holding a `when()` key, alone and spread over an earlier key. */
export const resolvedWhenSpread = /* @__PURE__ */ annotatePageProps<ResolvedWhenSpreadPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'props-walk.resolved-when-spread',
    url: '/props-walk/resolved-when-spread',
    methods: ['get', 'head'] as const,
    component: 'PropsWalk/ResolvedWhenSpread',
}));

/**
 * Props a block builds and renders, a default over a key a branch may skip, a spread resolve() (GH-161 B1, B4, B5, A9).
 *
 * @see Workbench\App\Http\Controllers\InertiaPropsWalkController
 */
const InertiaPropsWalkController = {
    blockBuilt,
    exitingKeyWrite,
    exitingNestedWhole,
    coalescedSelfRead,
    plusOperator,
    resolvedWhenSpread,
};

export default InertiaPropsWalkController;
