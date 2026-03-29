import { defineRoute } from '@tolki/ts/routes';

export const invokable = defineRoute({
    name: 'named.invokable',
    url: '/named-invokable',
    methods: ['get'] as const,
});

/**
 * Handles named invokable requests
 *
 * @see Workbench\App\Http\Controllers\NamedInvokableController
 */
const NamedInvokableController = {
    invokable,
};

export default NamedInvokableController;
