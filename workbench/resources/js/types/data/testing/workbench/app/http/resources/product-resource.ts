import type { ProductJsonMetaData, ProductMetadata } from '@js/types/product';
import type { ImageResource, TagResource } from '.';

/**
 * Exercises: multiple whenAggregated (sum/min/max), whenNotNull, when,
 * whenCounted, two mergeWhen blocks, Resource::collection x2.
 *
 * @see Workbench\App\Http\Resources\ProductResource
 */
export interface ProductResource
{
    id: string;
    name: string;
    slug: string;
    sku: string;
    description: string | null;
    price: string;
    compare_at_price?: string;
    cost_price?: string | null;
    quantity: number;
    is_active: boolean;
    is_featured: boolean;
    published_at?: string;
    tags?: TagResource[];
    images?: ImageResource[];
    orders_count?: number;
    total_sold?: number | null;
    min_unit_price?: number | null;
    max_unit_price?: number | null;
    weight?: number | null;
    dimensions?: { length: number; width: number; height: number; unit: "cm" | "in" };
    metadata?: ProductMetadata | ProductJsonMetaData | null;
}
