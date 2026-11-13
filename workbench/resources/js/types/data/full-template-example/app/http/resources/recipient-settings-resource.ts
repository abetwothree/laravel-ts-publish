/**
 * Maps a value object's recipients to each one's cached settings, a read no rule types, under a `toArray()` `@return`
 * docblock that types it (GH-154): the docblock fills that member alone, and the body types the name.
 *
 * @see Workbench\App\Http\Resources\RecipientSettingsResource
 */
export interface RecipientSettingsResource
{
    recipients: { name: string | null; settings: { digest: boolean } | null }[];
}
