import { defineRoute } from '@tolki/ts';

export const index = /* @__PURE__ */ defineRoute({
    name: 'prism.index',
    url: '/prism',
    methods: ['get', 'head'] as const,
});

/** @see Workbench\App\Http\Controllers\Prism\PrismController */
const PrismController = {
    index,
};

export default PrismController;
