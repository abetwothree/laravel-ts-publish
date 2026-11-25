import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../../enums';

/**
 * A helper's declared shape alternating the app's and the CRM's `Status` over an untyped read (GH-81). Each token names
 * the enum its member declares.
 *
 * @see Workbench\App\Http\Resources\SameNamed\DeclaredStatusesResource
 */
export interface DeclaredStatusesResource
{
    statuses: { a: WorkbenchStatusType; b: CrmStatusType; c: WorkbenchStatusType | null };
}
