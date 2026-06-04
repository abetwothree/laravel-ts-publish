/** @see Workbench\App\Http\Requests\StorePostRequest */
export interface StorePostRequest {
    title: string;
    body: string;
    published?: boolean;
    rating?: number | null;
    /** @format email */
    email: string;
    tags?: string[];
    "tags.*"?: string;
}
