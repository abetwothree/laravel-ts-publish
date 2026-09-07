import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { Post, User } from '../../models';

export type DashboardPageProps = Inertia.SharedData & { stats: { users: number; posts: number; views: number }, recentActivity: never[] };

/** Display the dashboard page. */
export const dashboard = annotatePageProps<DashboardPageProps>()(defineRoute({
    name: 'inertia.dashboard',
    url: '/inertia/dashboard',
    methods: ['get', 'head'] as const,
    component: 'Dashboard',
}));

export type SettingsPageProps = Inertia.SharedData & { user: { name: string; email: string }, preferences: { theme: string; notifications: boolean } };

/** Display the settings page. */
export const settings = annotatePageProps<SettingsPageProps>()(defineRoute({
    name: 'inertia.settings',
    url: '/inertia/settings',
    methods: ['get', 'head'] as const,
    component: 'Settings/General',
}));

export type AboutPageProps = Inertia.SharedData;

/** Display the about page (no props). */
export const about = annotatePageProps<AboutPageProps>()(defineRoute({
    name: 'inertia.about',
    url: '/inertia/about',
    methods: ['get', 'head'] as const,
    component: 'About',
}));

export type ConditionalAuthenticatedPageProps = Inertia.SharedData & { user: User | null };
export type ConditionalGuestPageProps = Inertia.SharedData & { message: string };

/** Conditional rendering based on auth state. */
export const conditional = annotatePageProps<ConditionalAuthenticatedPageProps | ConditionalGuestPageProps>()(defineRoute({
    name: 'inertia.conditional',
    url: '/inertia/conditional',
    methods: ['get', 'head'] as const,
    component: {authenticated: 'Conditional/Authenticated', guest: 'Conditional/Guest'} as const,
}));

export type PostPageProps = Inertia.SharedData & { post: Post };

/** Display a specific post. */
export const post = annotatePageProps<PostPageProps>()(defineRoute({
    name: 'inertia.post',
    url: '/inertia/post/{post}',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'PostShow',
}));

/** @see Workbench\App\Http\Controllers\InertiaController */
const InertiaController = {
    dashboard,
    settings,
    about,
    conditional,
    post,
};

export default InertiaController;
