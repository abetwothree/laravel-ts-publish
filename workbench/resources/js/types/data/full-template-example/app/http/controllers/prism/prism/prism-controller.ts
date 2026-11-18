import { defineRoute } from '@tolki/ts';

export const nested = /* @__PURE__ */ defineRoute({
    name: 'prism.prism.nested',
    url: '/prism/nested',
    methods: ['get', 'head'] as const,
});

/** @see Workbench\App\Http\Controllers\Prism\Prism\PrismController */
const PrismController = {
    nested,
};

export default PrismController;
