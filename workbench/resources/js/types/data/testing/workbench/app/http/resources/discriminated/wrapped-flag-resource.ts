/**
 * Typed properties PHP holds as objects whose override types them as scalars: a guard tests the object, not what
 * json_encode() writes, so neither is a discriminant and every key publishes flat (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\WrappedFlagResource
 */
export interface WrappedFlagResource
{
    flag: boolean;
    flag_title: string | null;
    mode: 'on' | 'off';
    mode_title: string | null;
}
