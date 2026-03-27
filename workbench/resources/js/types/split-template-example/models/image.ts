import { type AsEnum } from '@tolki/enum';

import { Status } from '../enums';
import type { MenuSettingsType } from '@js/types/settings';
import type { StatusType } from '../enums';
import type { User } from './';

export interface Image
{
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
}

export interface ImageMutators
{
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
    uploader_from_docblock: User | null;
    config_from_docblock: MenuSettingsType;
    data_from_docblock: unknown[];
    label_from_docblock: string;
    no_docblock_accessor: unknown;
    wrong_format_docblock: string | null;
    positive_int_accessor: number;
    numeric_string_accessor: string;
}

export interface ImageMutatorsResource extends Omit<ImageMutators, 'status_from_docblock'>
{
    status_from_docblock: AsEnum<typeof Status> | null;
}

export interface ImageRelations
{
    // Relations
    /** Polymorphic parent (Product, Post, User, etc.) */
    imageable: Image;
    // Counts
    imageable_count: number;
    // Exists
    imageable_exists: boolean;
}

export interface ImageAll extends Image, ImageMutators, ImageRelations {}

export interface ImageAllResource extends Image, ImageMutatorsResource, ImageRelations {}
