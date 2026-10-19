import type { Report as MarketingReportReport } from '../../models/marketing/report';
import type { Report as SalesReportReport } from '../../models/sales/report';

/**
 * Fixture: Kpi::reportable() morphs to two Report models sharing basename and parent segment,
 * reproducing a same-basename alias collision through a resource instead of a model.
 *
 * @see Workbench\App\Http\Resources\KpiResource
 */
export interface KpiResource
{
    reportable?: MarketingReportReport | SalesReportReport;
}
