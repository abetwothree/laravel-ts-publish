/**
 * Get the validation rules that apply to the request.
 *
 * @see Workbench\App\Http\Requests\DatabaseRulesRequest
 */
export interface DatabaseRulesRequest {
    /** @constraint exists */
    state: string;
    /** @constraint exists */
    category_id: number;
    /** @constraint exists */
    country_code: string;
    /**
     * @format email
     * @constraint unique
     */
    email: string;
    /** @constraint unique */
    username: string;
    /** @constraint unique */
    phone?: string | null;
    /** @constraint exists */
    parent_id?: number | null;
}
