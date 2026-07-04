import type { User as AppUser } from '../../app/models';
import type { User as CrmUser } from '../models';

/** @see Workbench\Crm\Events\UserSynced */
export interface UserSynced {
    user: Partial<AppUser>;
    crmUser: Partial<CrmUser>;
}
