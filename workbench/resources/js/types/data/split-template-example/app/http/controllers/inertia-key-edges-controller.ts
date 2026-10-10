import { defineRoute, annotatePageProps } from '@tolki/ts';

export type ShowPageProps = Inertia.SharedData & { "can-edit": boolean, ok: number };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'key-edges.show',
    url: '/key-edges',
    methods: ['get', 'head'] as const,
    component: 'KeyEdges/Show',
}));

/**
 * Renders a prop whose key is not an identifier, so the page type must quote it.
 *
 * @see Workbench\App\Http\Controllers\InertiaKeyEdgesController
 */
const InertiaKeyEdgesController = {
    show,
};

export default InertiaKeyEdgesController;
