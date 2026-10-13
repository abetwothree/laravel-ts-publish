/**
 * A whenLoaded() closure parameter its body reassigns: a read past the reassignment takes what the body assigned,
 * and a read before it, past a branch that reassigns it and returns, or a reassignment to the same model, keeps the
 * parameter's own binding.
 *
 * @see Workbench\App\Http\Resources\ReassignedParameterResource
 */
export interface ReassignedParameterResource
{
    declared_content?: string;
    read_before?: string;
    same_model?: string;
    relation_bio?: string | null;
    exiting_branch?: string | null;
    new_model_name?: string | null;
}
