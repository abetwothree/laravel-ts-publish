import type { ReviewSubject } from '@js/types/reviews';

/**
 * Casts the key that reads two resources named `UserResource` to a type of the app's own, so neither resource is
 * imported.
 *
 * @see Workbench\App\Http\Resources\ImageReviewCastResource
 */
export interface ImageReviewCastResource
{
    id: number;
    reviewable?: ReviewSubject | null;
}
