import type { ReviewerCard } from '@js/types/reviews';
import type { User } from '../../crm/models';

/** @see Workbench\App\Events\ReviewerCastEvent */
export interface ReviewerCastEvent {
    reviewer: ReviewerCard | null;
    contact: Partial<User>;
}
