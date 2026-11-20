import { defineRoute } from '@tolki/ts';

export const show = /* @__PURE__ */ defineRoute({
    name: 'key-name.show',
    url: '/key-name-test/{customKeyPost}',
    methods: ['get', 'head'] as const,
    args: [{name: 'customKeyPost', required: true, _routeKey: 'custom_key'}] as const,
});

/** @see Workbench\App\Http\Controllers\CustomKeyNameController */
const CustomKeyNameController = {
    show,
};

export default CustomKeyNameController;
