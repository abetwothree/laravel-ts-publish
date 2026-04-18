import { defineRoute } from '@tolki/ts';

export const index = defineRoute({
    name: 'domain.index',
    url: 'api.example.com/domain',
    domain: 'api.example.com',
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\DomainController
 */
const DomainController = {
    index,
};

export default DomainController;
