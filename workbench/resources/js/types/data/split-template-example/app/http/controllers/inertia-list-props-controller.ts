import { defineRoute, annotatePageProps } from '@tolki/ts';

export type ShowPageProps = Inertia.SharedData & { stages: string[], lanes: number[], override: { code: string; lane: number } };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'list-props.show',
    url: '/list-props',
    methods: ['get', 'head'] as const,
    component: 'ListProps/Show',
}));

/**
 * Renders list literals and an array union as page props.
 *
 * @see Workbench\App\Http\Controllers\InertiaListPropsController
 */
const InertiaListPropsController = {
    show,
};

export default InertiaListPropsController;
