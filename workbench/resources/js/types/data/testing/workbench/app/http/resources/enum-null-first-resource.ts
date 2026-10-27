import type { MediaTypeType } from '../../enums';

/**
 * Resource for testing @var null|Type docblock ordering (null-first convention).
 *
 * @see Workbench\App\Http\Resources\EnumNullFirstResource
 */
export interface EnumNullFirstResource
{
    value: MediaTypeType;
}
