/**
 * A JSON:API resource a nullable to-one relationship names.
 *
 * @see Workbench\App\Http\Resources\JsonApi\CategoryResource
 */
export interface CategoryResource
{
    id: string;
    type: 'categories';
    attributes: { name: string; slug: string };
}
