import { defineRoute } from '@tolki/ts';

export const index = /* @__PURE__ */ defineRoute({
    name: 'domain.index',
    url: 'api.example.com/domain',
    domain: 'api.example.com',
    methods: ['get', 'head'] as const,
});

/** @see Workbench\App\Http\Controllers\DomainController */
const DomainController = {
    index,
};

export default DomainController;
