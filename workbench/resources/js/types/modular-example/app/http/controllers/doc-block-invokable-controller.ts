import { defineRoute } from '@tolki/ts/routes';

/** Performs the invokable action. */
export const invoke = defineRoute({
    name: 'docblock.invokable',
    url: '/docblock-invokable',
    methods: ['get'] as const,
});

/**
 * Controller-level description.
 *
 * @see Workbench\App\Http\Controllers\DocBlockInvokableController
 */
const DocBlockInvokableController = {
    '__invoke': invoke,
};

export default DocBlockInvokableController;
