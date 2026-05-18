import { defineRoute } from '@tolki/ts';

export const showInt = defineRoute({
    name: 'typed.show-int',
    url: '/typed/{id}',
    methods: ['get'] as const,
    args: [{name: 'id', required: true, where: '[0-9]+'}] as const,
});

export const showRole = defineRoute({
    name: 'typed.show-role',
    url: '/typed/role/{role}',
    methods: ['get'] as const,
    args: [{name: 'role', required: true}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\TypedParamController
 */
const TypedParamController = {
    showInt,
    showRole,
};

export default TypedParamController;
