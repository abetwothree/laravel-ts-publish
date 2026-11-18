import { defineRoute } from '@tolki/ts';

export const showInt = /* @__PURE__ */ defineRoute({
    name: 'typed.show-int',
    url: '/typed/{id}',
    methods: ['get', 'head'] as const,
    args: [{name: 'id', required: true, where: '[0-9]+'}] as const,
});

export const showRole = /* @__PURE__ */ defineRoute({
    name: 'typed.show-role',
    url: '/typed/role/{role}',
    methods: ['get', 'head'] as const,
    args: [{name: 'role', required: true}] as const,
});

/** @see Workbench\App\Http\Controllers\TypedParamController */
const TypedParamController = {
    showInt,
    showRole,
};

export default TypedParamController;
