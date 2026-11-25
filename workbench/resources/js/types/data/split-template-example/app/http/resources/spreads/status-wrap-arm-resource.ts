import { type AsEnum } from '@tolki/ts';

import { Status } from '../../../enums';

/**
 * An arm holding a number key beside an enum wrap (GH-161 D3).
 *
 * @see Workbench\App\Http\Resources\Spreads\StatusWrapArmResource
 */
export interface StatusWrapArmResource
{
    id: number;
    name: string;
    status: AsEnum<typeof Status>;
}
