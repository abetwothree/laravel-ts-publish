/**
 * Resource with non-conventional name — tests #[UseResource] attribute model resolution. The backing model (TrackingEvent) uses #[UseResource(EventLogResource::class)] to point to this resource since it can't be found by naming convention.
 *
 * @see Workbench\App\Http\Resources\EventLogResource
 */
export interface EventLogResource
{
    id: number;
    description: string | null;
}
