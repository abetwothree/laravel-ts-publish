import type { StatusType as AppStatusType } from '../../app/enums';
import type { StatusType as CrmStatusType } from '../enums';

/** @see Workbench\Crm\Events\StatusSynced */
export interface StatusSynced {
    status: AppStatusType;
    crmStatus: CrmStatusType;
}
