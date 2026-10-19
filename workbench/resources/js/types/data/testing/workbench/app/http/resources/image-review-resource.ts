import type { UserResource as CrmUserResource } from '../../../crm/http/resources';
import type { UserResource as WorkbenchUserResource } from '.';

/**
 * Exposes a morphTo whose targets' resources share a basename: Crm's UserResource and this namespace's UserResource
 * must each be named by its own alias, inside an inline array as well.
 *
 * @see Workbench\App\Http\Resources\ImageReviewResource
 */
export interface ImageReviewResource
{
    id: number;
    reviewable?: CrmUserResource | WorkbenchUserResource | null;
    reviewer: CrmUserResource | WorkbenchUserResource;
    review: { subject: CrmUserResource | WorkbenchUserResource; label: string | null };
}
