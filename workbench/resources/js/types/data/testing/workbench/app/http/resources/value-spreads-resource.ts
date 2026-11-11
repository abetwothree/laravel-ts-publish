import type { MailRecipientResource } from '.';

/**
 * Spreads a value that writes a key again, after a helper or a key and before a resource arm (GH-154).
 *
 * @see Workbench\App\Http\Resources\ValueSpreadsResource
 */
export interface ValueSpreadsResource
{
    helper_then_value: { id: number; by: string };
    key_then_value: { by: string };
    helper_then_optional: { id: number; by: { name: string | null; email: string | null } | string };
    value_then_arm: Omit<{ name: number }, keyof MailRecipientResource> & MailRecipientResource;
    key_then_arm: Omit<{ name?: number }, keyof MailRecipientResource> & MailRecipientResource;
    merged_when: { id: number; by?: string };
    by: unknown;
}
