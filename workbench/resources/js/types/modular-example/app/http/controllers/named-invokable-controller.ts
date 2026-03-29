import { defineRoute } from '@tolki/ts/routes';

export const invokable = defineRoute({
    name: 'named.invokable',
    url: '/named-invokable',
    domain: null,
    methods: ['get'] as const,
});

/**
 * Handles named invokable requests
 *
 * @see Workbench\App\Http\Controllers\NamedInvokableController
 */
const NamedInvokableController = {
    '__invoke': invokable,
};

export default NamedInvokableController;
