import type { User as CrmUser } from '../../crm/models';
import type { User as AppUser } from '../models';

/** @see Workbench\App\Events\SameBasenameModelEvent */
export interface SameBasenameModelEvent {
    actor: AppUser | CrmUser;
}
