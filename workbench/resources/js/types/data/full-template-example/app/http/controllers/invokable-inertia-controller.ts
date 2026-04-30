import { defineRoute } from '@tolki/ts';

export const invoke = defineRoute({
    name: 'inertia.profile',
    url: '/inertia/profile',
    methods: ['get'] as const,
    component: 'Profile',
});

/**
 * @see Workbench\App\Http\Controllers\InvokableInertiaController
 */
const InvokableInertiaController = {
    '__invoke': invoke,
};

export default InvokableInertiaController;
