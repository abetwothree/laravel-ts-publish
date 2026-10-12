import type { StatusType as CrmStatusType } from '../../crm/enums';
import type { StatusType as AppStatusType } from '../enums';

/** @see Workbench\App\Events\PostStatusBroadcast */
export interface PostStatusBroadcast {
    status: AppStatusType;
    crm_status: CrmStatusType;
    kept: AppStatusType;
}
