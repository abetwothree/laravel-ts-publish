import { defineRoute } from '@tolki/ts';

/** Authenticate the request for channel access. */
export const authenticate = /* @__PURE__ */ defineRoute({
    url: '/broadcasting/auth',
    methods: ['get', 'post', 'head'] as const,
});

/** @see Illuminate\Broadcasting\BroadcastController */
const BroadcastController = {
    authenticate,
};

export default BroadcastController;
