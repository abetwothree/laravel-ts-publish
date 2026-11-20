import type { AttachmentResource } from '@js/types/wire';
import type { PriorityType } from '../../../enums';

/**
 * A JSON:API resource whose class cast keeps the text inference gives an attribute the priority picks, but imports its
 * class from elsewhere: the attributes publish flat, with the cast's class (GH-157).
 *
 * @see Workbench\App\Http\Resources\JsonApi\WireAttachmentPostResource
 */
export interface WireAttachmentPostResource
{
    id: string;
    type: 'wire_attachment_posts';
    attributes: { priority: PriorityType | null; attachment: AttachmentResource | null };
}
