import { defineRoute } from '@tolki/ts';

export const show = defineRoute({
    name: 'key-name.show',
    url: '/key-name-test/{customKeyPost}',
    methods: ['get'] as const,
    args: [{name: 'customKeyPost', required: true, _routeKey: 'custom_key'}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\CustomKeyNameController
 */
const CustomKeyNameController = {
    show,
};

export default CustomKeyNameController;
