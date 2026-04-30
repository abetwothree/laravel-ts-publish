import type { ProductJsonMetaData, ProductMetadata } from '@js/types/product';
import type { Image, OrderItem, Tag } from '.';

/**
 * @see Workbench\App\Models\Product
 */
export interface Product
{
    // Columns
    id: string;
    name: string;
    slug: string;
    sku: string;
    description: string | null;
    price: number;
    compare_at_price: number | null;
    cost_price: number | null;
    quantity: number;
    weight: number | null;
    dimensions: { length: number; width: number; height: number; unit: "cm" | "in" };
    is_active: boolean;
    is_featured: boolean;
    published_at: string | null;
    metadata: ProductMetadata | ProductJsonMetaData | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    // Mutators
    /** Whether the product is on sale */
    is_on_sale: boolean;
    /** Discount percentage (0-100) or null */
    discount_percentage: number | null;
    /** Profit margin percentage */
    profit_margin: number | null;
    /** Whether the product is in stock */
    in_stock: boolean;
    // Relations
    order_items: OrderItem[];
    /** Polymorphic many-to-many with tags */
    tags: Tag[];
    /** Polymorphic one-to-many with images */
    images: Image[];
    // Counts
    order_items_count: number;
    tags_count: number;
    images_count: number;
    // Exists
    order_items_exists: boolean;
    tags_exists: boolean;
    images_exists: boolean;
}
