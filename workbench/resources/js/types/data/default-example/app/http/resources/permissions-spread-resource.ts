/**
 * Every return branch of a spread method counts, and the method's own @return shape types
 * what its body cannot.
 *
 * @see Workbench\App\Http\Resources\PermissionsSpreadResource
 */
export interface PermissionsSpreadResource
{
    permissions?: Record<string, boolean>;
    links?: { self: string; related: Record<string, { name: string }> };
    main_label: string;
    extra_label?: string;
    id: number;
}
