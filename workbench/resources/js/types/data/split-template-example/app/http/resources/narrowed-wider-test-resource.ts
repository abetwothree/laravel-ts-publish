import type { User } from '../../models';

/**
 * `instanceof` tests that name a supertype, an interface or a sibling of what the subject already holds. The arm a
 * test proves reads the subject as what the test leaves of its own classes, so a wider test never widens it.
 *
 * @see Workbench\App\Http\Resources\NarrowedWiderTestResource
 */
export interface NarrowedWiderTestResource
{
    negated_model_title: string | null;
    negated_interface_title: string | null;
    negated_interface_email: string | null;
    negated_resource_title: string | null;
    negated_model_author: User | null;
    sibling_chain_title: string | null;
    supertype_chain_title: string | null;
    interface_chain_email: string | null;
    negated_chain_email: string | null;
    positive_model_title: string | null;
}
