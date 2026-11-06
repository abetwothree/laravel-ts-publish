/**
 * Maps a value object's recipient lists through array_map() under a `toArray()` `@return` docblock (GH-154): an
 * untyped callback parameter over a promoted and a plain `@var list<MailRecipient>` property, and a typed one.
 *
 * @see Workbench\App\Http\Resources\DocumentedRecipientsResource
 */
export interface DocumentedRecipientsResource
{
    recipients: { name: unknown; email: unknown }[];
    copied: { name: unknown; email: unknown }[];
    typed: { name: string | null; email: string | null }[];
}
