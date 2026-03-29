import { defineRoute } from '@tolki/ts/routes';

/** This action is included */
export const show = defineRoute({
    name: 'excludable.show',
    url: '/excludable/{id}',
    domain: null,
    methods: ['get'] as const,
    args: [{name: 'id', required: true}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\ExcludableController
 */
const ExcludableController = {
    show,
};

export default ExcludableController;
