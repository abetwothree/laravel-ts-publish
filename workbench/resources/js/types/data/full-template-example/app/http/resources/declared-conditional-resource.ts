import type { User } from '../../models';

/**
 * Locals holding a conditional value the engine cannot read, or read inside a whenLoaded() closure. A key the `@var`
 * types stays optional, since Laravel drops it when the condition fails, and inside the closure a known reading of
 * the assigned value stands over the loaded relation's model.
 *
 * @see Workbench\App\Http\Resources\DeclaredConditionalResource
 */
export interface DeclaredConditionalResource
{
    reviewer?: User;
    flags?: { a: number };
    views?: number;
    author_name?: string;
}
