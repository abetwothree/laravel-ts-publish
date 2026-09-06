import { type AsEnum } from '@tolki/ts';

import { Size, Status } from '../enums';
import type { MenuSettingsType } from '@js/types/settings';
import type { User as CrmUser } from '../../crm/models';
import type { SizeType, StatusType } from '../enums';
import type { Post, Product, User as ModelsUser } from '.';

/** @see Workbench\App\Models\Image */
export interface Image
{
    // Columns
    id: number;
    imageable_type: string;
    imageable_id: number;
    url: string;
    alt_text: string | null;
    disk: string;
    path: string;
    mime_type: string;
    size_bytes: number;
    width: number | null;
    height: number | null;
    sort_order: number;
    metadata: unknown[] | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** Human-readable file size */
    size_for_humans: string;
    /** Whether the image is landscape orientation */
    is_landscape: boolean;
    /** Aspect ratio as a string (e.g. "16:9") or null if dimensions not set */
    aspect_ratio: string | null;
    extension: string | null;
    /** This is the size test to parse from the docblock in the test for accessor type resolution. */
    size: number;
    flexible_id: string | number | null;
    optional_label: string | null;
    status_from_docblock: StatusType | null;
    /** Shirt size, typed by an enum whose #[TsEnum(name:)] differs from its class basename. */
    shirt_size: SizeType;
    uploader_from_docblock: ModelsUser | null;
    config_from_docblock: MenuSettingsType;
    data_from_docblock: { recordedAt?: string; title: string; weight: number | null };
    nested_optional_key_from_docblock: { inner: { assignedLater?: string; promoted: string }; label: string };
    uploaders_from_docblock: ModelsUser[] | Record<string, ModelsUser>;
    uploaders_from_docblock_int: ModelsUser[];
    uploaders_from_docblock_string: Record<string, ModelsUser>;
    tree_from_docblock: { label: string; child: unknown[] };
    price_from_docblock: { amount: number; currency: string };
    label_from_docblock: string;
    no_docblock_accessor: unknown;
    wrong_format_docblock: string | null;
    positive_int_accessor: number;
    numeric_string_accessor: string;
    // Relations
    /** Polymorphic parent (Product, Post, User, etc.) */
    imageable: Post | Product | ModelsUser | CrmUser;
    reviewable: CrmUser | ModelsUser | null;
    // Counts
    imageable_count: number;
    reviewable_count: number;
    // Exists
    imageable_exists: boolean;
    reviewable_exists: boolean;
}

export interface ImageResource extends Omit<Image, 'status_from_docblock' | 'shirt_size'>
{
    status_from_docblock: AsEnum<typeof Status> | null;
    shirt_size: AsEnum<typeof Size>;
}
