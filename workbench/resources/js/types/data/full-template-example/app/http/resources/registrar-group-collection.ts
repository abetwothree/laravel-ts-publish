/**
 * The #[UseResourceCollection] target for Registrar. Deliberately declares no $collects and has
 * no matching RegistrarGroupResource/RegistrarGroup class, so its element type is undeterminable:
 * its items list as unknown, and Registrar's toResourceCollection() never falls through to RegistrarResource.
 *
 * @see Workbench\App\Http\Resources\RegistrarGroupCollection
 */
export interface RegistrarGroupCollection
{
    data: unknown[];
}
