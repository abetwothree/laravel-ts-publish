import { type AsEnum } from '@tolki/ts';

import { Status } from '../../enums';
import type { StatusType } from '../../enums';

/**
 * A mixed EnumResource/direct-access ternary nested one level down, where both arms read the same
 * list-shaped accessor: they render the same string and the union merge collapses them, so only
 * each arm's own recorded shape still says the [] belongs on both. The direct arm's bare enum type
 * survives the rewrite, so its type import has to come back with it.
 *
 * @see Workbench\App\Http\Resources\TeamStatusAuditResource
 */
export interface TeamStatusAuditResource
{
    id: number;
    audit: { status: AsEnum<typeof Status>[] | StatusType[] };
}
