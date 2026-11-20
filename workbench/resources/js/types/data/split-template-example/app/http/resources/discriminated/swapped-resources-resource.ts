import type { UserResource as CrmUserResource } from '../../../../crm/http/resources';
import type { UserResource as ResourcesUserResource } from '..';

/**
 * Arms that spell one text, each binding the two same-named resources in the other order: Low and Medium stay apart,
 * at the top level and nested (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\SwappedResourcesResource
 */
export type SwappedResourcesResource = {
    meta: { priority: 0; pair: { primary: ResourcesUserResource; secondary: CrmUserResource } } | { priority: 1; pair: { primary: CrmUserResource; secondary: ResourcesUserResource } } | { priority: 2 | 3 | null; pair: null };
} & (
    | { priority: 0; pair: { primary: ResourcesUserResource; secondary: CrmUserResource } }
    | { priority: 1; pair: { primary: CrmUserResource; secondary: ResourcesUserResource } }
    | { priority: 2 | 3 | null; pair: null }
);
