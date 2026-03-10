import type { ProductMetadata, ProductJsonMetaData } from '@js/types/product';
import type { OrderItem, Tag, Image } from '.';

export interface Product
{
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
}

export interface ProductMutators
{
    is_on_sale: boolean;
    discount_percentage: number | null;
    profit_margin: number | null;
    in_stock: boolean;
}

export interface ProductRelations
{
    // Relations
    order_items: OrderItem[];
    tags: Tag[];
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

export interface ProductAll extends Product, ProductMutators, ProductRelations {}
