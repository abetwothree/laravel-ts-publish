/**
 * Starts its variable from `parent::toArray()`, the model's own serialization, and adds one key.
 *
 * `Label` has no relation and no accessor, so the delegated base publishes only keys the response carries.
 *
 * @see Workbench\App\Http\Resources\ReturnedParentVariableResource
 */
export interface ReturnedParentVariableResource
{
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
    display_name: string;
}
