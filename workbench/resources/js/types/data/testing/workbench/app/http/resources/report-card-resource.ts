import type { SummaryCardResource } from './report-cards';

/**
 * Nests a resource from a multi-word namespace segment, so the globals file qualifies it across namespaces.
 *
 * @see Workbench\App\Http\Resources\ReportCardResource
 */
export interface ReportCardResource
{
    id: number;
    summary: SummaryCardResource;
}
