import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { SizeType } from '../../enums';

export type ShowPageProps = Inertia.SharedData & { size: SizeType };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'shirts.show',
    url: '/shirts/{image}',
    methods: ['get', 'head'] as const,
    args: [{name: 'image', required: true, _routeKey: 'id'}] as const,
    component: 'Shirts/Show',
}));

/**
 * Renders a page prop typed by an enum whose exported TypeScript name differs from its class basename.
 *
 * @see Workbench\App\Http\Controllers\InertiaShirtSizeController
 */
const InertiaShirtSizeController = {
    show,
};

export default InertiaShirtSizeController;
