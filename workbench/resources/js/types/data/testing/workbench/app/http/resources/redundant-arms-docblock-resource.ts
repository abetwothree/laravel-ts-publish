import type { MediaTypeType, StatusType } from '../../enums';

/**
 * A `@return` that types an arm the body cannot, beside a case value its enum's alias admits (GH-166).
 *
 * @see Workbench\App\Http\Resources\RedundantArmsDocblockResource
 */
export interface RedundantArmsDocblockResource
{
    status: StatusType | number;
    media: MediaTypeType;
}
