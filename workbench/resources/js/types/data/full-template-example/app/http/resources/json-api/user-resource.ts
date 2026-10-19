/**
 * A JSON:API resource naming its attributes only.
 *
 * @see Workbench\App\Http\Resources\JsonApi\UserResource
 */
export interface UserResource
{
    id: string;
    type: 'users';
    attributes: { name: string; email: string };
}
