import type { ProductJsonMetaData, ProductMetadata } from '@js/types/product';
import type { ImageResource, TagResource } from '.';

/** Exercises: multiple whenAggregated (sum/min/max), whenNotNull, when, whenCounted, two mergeWhen blocks, Resource::collection x2. */
export interface ProductResource
{
    id: string;
    name: string;
    slug: string;
    sku: string;
    description: string | null;
    price: number;
    compare_at_price?: number;
    cost_price?: number;
    quantity: number;
    is_active: boolean;
    is_featured: boolean;
    published_at?: string;
    tags?: TagResource[];
    images?: ImageResource[];
    orders_count?: number;
    total_sold?: number;
    min_unit_price?: number;
    max_unit_price?: number;
    weight?: number;
    dimensions?: { length: number; width: number; height: number; unit: "cm" | "in" };
    metadata?: ProductMetadata | ProductJsonMetaData | null;
}
