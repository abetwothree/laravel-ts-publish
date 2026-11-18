import type { PriorityType } from '@js/types/wire';

/**
 * A JSON:API resource whose class cast keeps the text inference gives its discriminant attribute, but imports it from
 * elsewhere: the attributes publish flat, with the cast's import (GH-157).
 *
 * @see Workbench\App\Http\Resources\JsonApi\WirePriorityPostResource
 */
export interface WirePriorityPostResource
{
    id: string;
    type: 'wire_priority_posts';
    attributes: { priority: PriorityType | null; escalated_title: string | null };
}
