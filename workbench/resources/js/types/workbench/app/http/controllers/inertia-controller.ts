import { defineRoute } from '@tolki/ts';

/** Display the dashboard page. */
export const dashboard = defineRoute({
    name: 'inertia.dashboard',
    url: '/inertia/dashboard',
    methods: ['get'] as const,
    component: 'Dashboard',
});

/** Display the settings page. */
export const settings = defineRoute({
    name: 'inertia.settings',
    url: '/inertia/settings',
    methods: ['get'] as const,
    component: 'Settings/General',
});

/** Display the about page (no props). */
export const about = defineRoute({
    name: 'inertia.about',
    url: '/inertia/about',
    methods: ['get'] as const,
    component: 'About',
});

/** Conditional rendering based on auth state. */
export const conditional = defineRoute({
    name: 'inertia.conditional',
    url: '/inertia/conditional',
    methods: ['get'] as const,
    component: {authenticated: 'Conditional/Authenticated', guest: 'Conditional/Guest'} as const,
});

/**
 * @see Workbench\App\Http\Controllers\InertiaController
 */
const InertiaController = {
    dashboard,
    settings,
    about,
    conditional,
};

export default InertiaController;
