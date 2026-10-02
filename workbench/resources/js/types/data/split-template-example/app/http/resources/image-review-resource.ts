import type { UserResource as CrmUserResource } from '../../../crm/http/resources';
import type { UserResource as ResourcesUserResource } from '.';

/**
 * Exposes a morphTo whose targets' resources share a basename: Crm's UserResource and this namespace's UserResource
 * must each be named by its own alias, inside an inline array as well.
 *
 * @see Workbench\App\Http\Resources\ImageReviewResource
 */
export interface ImageReviewResource
{
    id: number;
    reviewable?: CrmUserResource | ResourcesUserResource;
    reviewer: CrmUserResource | ResourcesUserResource;
    review: { subject: CrmUserResource | ResourcesUserResource; label: string | null };
}
