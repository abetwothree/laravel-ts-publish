import type { StatusType } from '../../enums';

/**
 * Untyped properties of the resource's own, each typed from its default as a parameter default is. `name` shadows the
 * Depot column: PHP reads the declared property before JsonResource::__get() reaches the model, so the key sends null.
 *
 * @see Workbench\App\Http\Resources\DepotDefaultsResource
 */
export interface DepotDefaultsResource
{
    id: number;
    name: null;
    stage: StatusType;
    limits: { daily: number; codes: string[] };
    tags: (string | number)[];
    notes: never[];
    stage_name?: 'Draft' | 'Published';
}
