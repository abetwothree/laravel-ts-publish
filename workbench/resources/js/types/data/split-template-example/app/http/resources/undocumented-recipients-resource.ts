/**
 * Maps a value object's recipient lists through array_map() with no `toArray()` docblock (GH-154): an untyped callback
 * parameter over a promoted and a plain `@var list<MailRecipient>` property, and a typed one.
 *
 * @see Workbench\App\Http\Resources\UndocumentedRecipientsResource
 */
export interface UndocumentedRecipientsResource
{
    recipients: { name: string | null; email: string | null }[];
    copied: { name: string | null; email: string | null }[];
    typed: { name: string | null; email: string | null }[];
}
