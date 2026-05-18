import { defineRoute } from '@tolki/ts';

export const show = defineRoute({
    name: 'pk.show',
    url: '/pk-test/{uuidPost}',
    methods: ['get'] as const,
    args: [{name: 'uuidPost', required: true, _routeKey: 'uuid'}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\PrimaryKeyController
 */
const PrimaryKeyController = {
    show,
};

export default PrimaryKeyController;
