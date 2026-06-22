import { defineRoute, annotateRequestPayload } from '@tolki/ts';

import type { VerifyTwoFactorRequest } from '../requests/verify-two-factor-request';

/** Set up 2FA for the current user. */
export const setup = defineRoute({
    name: 'accounting.2fa-setup',
    url: '/accounting/2fa/setup',
    methods: ['get'] as const,
});

/** Verify a 2FA code. */
export const verify = annotateRequestPayload<VerifyTwoFactorRequest>()(defineRoute({
    name: 'accounting.2fa-verify',
    url: '/accounting/2fa/verify',
    methods: ['post'] as const,
}));

/**
 * Handles two-factor authentication for the accounting module.
 *
 * @see Workbench\Accounting\Http\Controllers\TwoFactorController
 */
const TwoFactorController = {
    setup,
    verify,
};

export default TwoFactorController;
