import { type AsEnum } from '@tolki/ts';

import { Status } from '../../enums';

/**
 * The direct arm's enum type is substituted away by the wrapped arm, so the bare enum type must not
 * be imported.
 *
 * @see Workbench\App\Http\Resources\TeamStatusAuditResource
 */
export interface TeamStatusAuditResource
{
    id: number;
    audit: { status: AsEnum<typeof Status>[] };
}
