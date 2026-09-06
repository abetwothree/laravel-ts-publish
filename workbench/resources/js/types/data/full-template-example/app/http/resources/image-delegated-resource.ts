import type { MenuSettingsType } from '@js/types/settings';
import type { User as CrmUser } from '../../../crm/models';
import type { StatusType } from '../../enums';
import type { Post, Product, User as ModelsUser } from '../../models';

/**
 * Same morphTo union, reached through the model-delegated analysis rather than an array literal.
 *
 * @see Workbench\App\Http\Resources\ImageDelegatedResource
 */
export interface ImageDelegatedResource
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
    size_for_humans: string;
    is_landscape: boolean;
    aspect_ratio: string | null;
    extension: string | null;
    size: number;
    flexible_id: string | number | null;
    optional_label: string | null;
    status_from_docblock: StatusType | null;
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
    imageable: Post | Product | ModelsUser | CrmUser;
    reviewable: CrmUser | ModelsUser | null;
}
