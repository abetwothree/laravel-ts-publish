import type { User as CrmUser } from '../../../crm/models';
import type { Post, Product, User as WorkbenchUser } from '../../models';

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
    imageable?: Post | Product | WorkbenchUser | CrmUser;
    reviewable?: CrmUser | WorkbenchUser | null;
}
