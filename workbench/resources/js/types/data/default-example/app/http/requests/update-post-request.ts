/**
 * @see Workbench\App\Http\Requests\UpdatePostRequest
 */
export interface UpdatePostRequest {
    title?: string;
    status: 'draft' | 'published' | 'archived';
    /** @constraint exists */
    category_id: number;
    priority?: number | null;
}
