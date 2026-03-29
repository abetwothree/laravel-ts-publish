import { defineRoute } from '@tolki/ts/routes';

export const index = defineRoute({
    name: 'prism.index',
    url: '/prism',
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\Prism\PrismController
 */
const PrismController = {
    index,
};

export default PrismController;
