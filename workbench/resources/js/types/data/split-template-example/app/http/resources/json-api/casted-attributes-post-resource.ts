import type { StatusType } from '../../../enums';

/**
 * A JSON:API resource naming an attribute the model's #[TsCasts] types, and casting another one itself.
 *
 * @see Workbench\App\Http\Resources\JsonApi\CastedAttributesPostResource
 */
export interface CastedAttributesPostResource
{
    id: string;
    type: 'casted_attributes_posts';
    attributes: { metadata: Record<string, {title: string, content: string}>; rating: `${number}` | null; status: StatusType };
}
