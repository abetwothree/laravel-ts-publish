import { defineRoute } from '@tolki/ts/routes';

export const nested = defineRoute({
    name: 'prism.prism.nested',
    url: '/prism/nested',
    domain: null,
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\Prism\Prism\PrismController
 */
const PrismController = {
    nested,
};

export default PrismController;
