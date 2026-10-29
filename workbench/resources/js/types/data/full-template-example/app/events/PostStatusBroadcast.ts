import type { StatusType as CrmStatusType } from '../../crm/enums';
import type { StatusKind as AppStatusKind, StatusType as AppStatusType } from '../enums';

/** @see Workbench\App\Events\PostStatusBroadcast */
export interface PostStatusBroadcast {
    status: AppStatusType;
    crm_status: CrmStatusType;
    kept: AppStatusType;
    status_name: AppStatusKind;
}
