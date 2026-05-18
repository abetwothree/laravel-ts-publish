import { defineRoute } from '@tolki/ts';

export const nested = defineRoute({
    name: 'prism.prism.nested',
    url: '/prism/nested',
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\Prism\Prism\PrismController
 */
const PrismController = {
    nested,
};

export default PrismController;
