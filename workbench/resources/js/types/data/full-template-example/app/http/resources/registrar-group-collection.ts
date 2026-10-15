/**
 * The #[UseResourceCollection] target for Registrar. Deliberately declares no $collects and has
 * no matching RegistrarGroupResource/RegistrarGroup class, so its element type is undeterminable —
 * this must degrade to unknown rather than silently falling through to RegistrarResource.
 *
 * @see Workbench\App\Http\Resources\RegistrarGroupCollection
 */
export interface RegistrarGroupCollection
{
    data: unknown[];
}
