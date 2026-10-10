import type { ReviewerCard } from '@js/types/reviews';
import type { User as CrmUser } from '../../crm/models';

/** @see Workbench\App\Events\ReviewerCastEvent */
export interface ReviewerCastEvent {
    reviewer: ReviewerCard | null;
    contact: Partial<CrmUser>;
}
