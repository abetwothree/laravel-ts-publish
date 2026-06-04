import { defineRoute, annotatePageProps, annotateRequestPayload } from '@tolki/ts';

import type { StorePostRequest } from '../requests/store-post-request';

export type CreatePageProps = Inertia.SharedData;

/**
  * Show the form for creating a new post.
  */
export const create = annotatePageProps<CreatePageProps>()(defineRoute({
    name: 'inertia-form-request.create',
    url: '/inertia-form-request/create',
    methods: ['get'] as const,
    component: 'InertiaFormRequest/Create',
}));

export type StorePageProps = Inertia.SharedData & { title: unknown };

/**
  * Store a new post validated via StorePostRequest.
  */
export const store = annotateRequestPayload<StorePostRequest>()(annotatePageProps<StorePageProps>()(defineRoute({
    name: 'inertia-form-request.store',
    url: '/inertia-form-request',
    methods: ['post'] as const,
    component: 'InertiaFormRequest/Success',
})));

/**
 * Demonstrates an Inertia controller that also uses FormRequest validation,
used by tests to verify the combined annotateRequestPayload + annotatePageProps output.
 *
 * @see Workbench\App\Http\Controllers\InertiaFormRequestController
 */
const InertiaFormRequestController = {
    create,
    store,
};

export default InertiaFormRequestController;
