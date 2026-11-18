import { defineRoute } from '@tolki/ts';

export const show = /* @__PURE__ */ defineRoute({
    name: 'pk.show',
    url: '/pk-test/{uuidPost}',
    methods: ['get', 'head'] as const,
    args: [{name: 'uuidPost', required: true, _routeKey: 'uuid'}] as const,
});

/** @see Workbench\App\Http\Controllers\PrimaryKeyController */
const PrimaryKeyController = {
    show,
};

export default PrimaryKeyController;
