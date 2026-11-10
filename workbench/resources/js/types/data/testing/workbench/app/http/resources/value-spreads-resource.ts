import type { MailRecipientResource } from '.';

/**
 * Spreads a value that writes a key again, after a helper or a key and before a resource arm (GH-154).
 *
 * @see Workbench\App\Http\Resources\ValueSpreadsResource
 */
export interface ValueSpreadsResource
{
    helper_then_value: Omit<{ by: string }, 'id' | 'by'> & { id: number; by: { name: string | null; email: string | null } };
    key_then_value: Omit<{ by: string }, 'by'> & { by: { name: string | null; email: string | null } };
    helper_then_optional: Omit<{ by?: string }, 'id' | 'by'> & { id: number; by: { name: string | null; email: string | null } };
    value_then_arm: Omit<{ name: number }, keyof MailRecipientResource> & MailRecipientResource;
    key_then_arm: Omit<{ name?: number }, 'name' | keyof MailRecipientResource> & Omit<MailRecipientResource, 'name'> & { name: { name: string | null; email: string | null } };
    by: { name: string | null; email: string | null };
}
