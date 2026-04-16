import type { OrderItem } from '../../models';
import type { AddressResource } from '.';

/**
 * Exercises analyzeInlineArray embeddedModelFqcns and embeddedResourceFqcns (lines 1501, 1508-1510) by returning inline arrays that contain whenLoaded() (model FQCN) and SomeResource::make() (resource FQCN) inside a closure union.
 *
 * @see Workbench\App\Http\Resources\InlineArrayFqcnResource
 */
export interface InlineArrayFqcnResource
{
    id: number;
    payload?: { address: AddressResource; items_loaded?: OrderItem[] } | null;
}
