import { defineRoute } from '@tolki/ts';

export const invoke = defineRoute({
    name: 'named.invokable',
    url: '/named-invokable',
    methods: ['get'] as const,
});

/**
 * Handles named invokable requests
 *
 * @see Workbench\App\Http\Controllers\NamedInvokableController
 */
const NamedInvokableController = invoke;

export default NamedInvokableController;
