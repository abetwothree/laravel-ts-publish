import { defineRoute, annotateRequestPayload } from '@tolki/ts';

import type { VerifyTwoFactorRequest } from '../requests/verify-two-factor-request';

/**
  * Set up 2FA for the current user.
  */
export const _2faSetup = defineRoute({
    name: 'accounting.2fa-setup',
    url: '/accounting/2fa/setup',
    methods: ['get'] as const,
});

/**
  * Verify a 2FA code.
  */
export const _2faVerify = annotateRequestPayload<VerifyTwoFactorRequest>()(defineRoute({
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
    'setup': _2faSetup,
    'verify': _2faVerify,
};

export default TwoFactorController;
