import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { Address } from '../resources';

export type ShowPageProps = Inertia.SharedData & { address: Address };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'addresses.show',
    url: '/addresses/{address}',
    methods: ['get', 'head'] as const,
    args: [{name: 'address', required: true, _routeKey: 'id'}] as const,
    component: 'Addresses/Show',
}));

/**
 * Renders a resource whose exported TypeScript name differs from its class basename.
 *
 * @see Workbench\App\Http\Controllers\InertiaAddressController
 */
const InertiaAddressController = {
    show,
};

export default InertiaAddressController;
