/**
 * Fixture for a resource whose model nothing can resolve — no TsResource attribute, no mixin or
 * extends tag, no typed $resource, no naming-convention match. Constructed over an Authorizable, so
 * `can()` forwards through JsonResource::__call and must type as boolean with the model arm gone.
 *
 * @see Workbench\App\Http\Resources\ViewerPermissionsResource
 */
export interface ViewerPermissionsResource
{
    can_publish: boolean;
}
