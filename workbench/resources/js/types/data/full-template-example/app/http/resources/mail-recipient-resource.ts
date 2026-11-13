/**
 * A recipient's name and email, the resource arm ValueSpreadsResource spreads (GH-154).
 *
 * @see Workbench\App\Http\Resources\MailRecipientResource
 */
export interface MailRecipientResource
{
    name: string | null;
    email: string | null;
}
