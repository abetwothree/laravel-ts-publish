import type { PostAttributes } from '@js/types/posts';

/** @see Workbench\App\Http\Requests\UpdatePostRequest */
export interface UpdatePostRequest {
    title?: string;
    status: 'draft' | 'published' | 'archived';
    /** @constraint exists */
    category_id: number;
    priority?: number | null;
    attributes?: PostAttributes;
}
