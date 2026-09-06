import type { StatusType as EnumsStatusType } from '../../../app/enums';
import type { StatusType as CrmStatusType } from '../../enums';

/**
 * Two Status enums sharing a basename catch what a distinct-named pair (Status/Priority)
 * cannot: aliasPropertyType() matches text, not FQCNs, so a repeat only misaligns once two
 * colliding enums force it to substitute per occurrence instead of reusing one bare name.
 *
 * @see Workbench\Crm\Http\Resources\DealEnumTrioResource
 */
export interface DealEnumTrioResource
{
    id: number;
    trio: { a: EnumsStatusType; b: CrmStatusType; c: EnumsStatusType };
    matrix: { a: EnumsStatusType; b: CrmStatusType; c: EnumsStatusType }[];
}
