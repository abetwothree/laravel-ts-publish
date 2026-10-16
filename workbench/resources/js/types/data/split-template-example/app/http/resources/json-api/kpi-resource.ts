import type { JsonApiToOneRelationship } from '@tolki/types';

/**
 * A JSON:API resource whose morph relationship reaches models with no API resource, so an include sends Laravel's
 * bare JsonApiResource over each, typed by the model's name.
 *
 * @see Workbench\App\Http\Resources\JsonApi\KpiResource
 */
export interface KpiResource
{
    id: string;
    type: 'kpis';
    attributes: { value: number };
    relationships?: { reportable?: JsonApiToOneRelationship<'reports'> };
}
