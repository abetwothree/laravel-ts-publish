import { defineRoute } from '@tolki/ts';

/** Performs the invokable action. */
export const invoke = /* @__PURE__ */ defineRoute({
    name: 'docblock.invokable',
    url: '/docblock-invokable',
    methods: ['get', 'head'] as const,
});

/**
 * Controller-level description.
 *
 * @see Workbench\App\Http\Controllers\DocBlockInvokableController
 */
const DocBlockInvokableController = invoke;

export default DocBlockInvokableController;
