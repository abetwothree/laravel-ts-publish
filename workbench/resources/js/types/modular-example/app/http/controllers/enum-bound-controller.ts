import { defineRoute } from '@tolki/ts/routes';

export const byStatus = defineRoute({
    name: 'posts.byStatus',
    url: '/posts/status/{status}',
    methods: ['get'] as const,
    args: [{name: 'status', required: true, _enumValues: [0, 1]}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\EnumBoundController
 */
const EnumBoundController = {
    byStatus,
};

export default EnumBoundController;
