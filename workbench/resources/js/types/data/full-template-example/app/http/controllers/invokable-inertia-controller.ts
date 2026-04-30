import { defineRoute, annotatePageProps } from '@tolki/ts';

export type InvokePageProps = Inertia.SharedData & { name: string };

export const invoke = annotatePageProps<InvokePageProps>()(defineRoute({
    name: 'inertia.profile',
    url: '/inertia/profile',
    methods: ['get'] as const,
    component: 'Profile',
}));

/**
 * @see Workbench\App\Http\Controllers\InvokableInertiaController
 */
const InvokableInertiaController = {
    '__invoke': invoke,
};

export default InvokableInertiaController;
