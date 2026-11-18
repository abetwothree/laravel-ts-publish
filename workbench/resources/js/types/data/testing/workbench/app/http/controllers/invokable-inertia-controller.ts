import { defineRoute, annotatePageProps } from '@tolki/ts';

export type InvokePageProps = Inertia.SharedData & { name: string };

export const invoke = /* @__PURE__ */ annotatePageProps<InvokePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'inertia.profile',
    url: '/inertia/profile',
    methods: ['get', 'head'] as const,
    component: 'Profile',
}));

/** @see Workbench\App\Http\Controllers\InvokableInertiaController */
const InvokableInertiaController = invoke;

export default InvokableInertiaController;
