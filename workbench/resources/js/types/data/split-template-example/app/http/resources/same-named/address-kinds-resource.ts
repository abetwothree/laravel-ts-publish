import type { Address as ModelsAddress } from '../../../models';
import type { Address as ResourcesAddress } from '..';

/**
 * The `Address` model beside `AddressResource`, which publishes as `Address` too (GH-76): side by side in an object, and
 * as the two arms of a ternary. Each value keeps its own class's alias.
 *
 * @see Workbench\App\Http\Resources\SameNamed\AddressKindsResource
 */
export interface AddressKindsResource
{
    data: { address: ResourcesAddress | null; raw: ModelsAddress | null };
    either: ModelsAddress | ResourcesAddress | null;
}
