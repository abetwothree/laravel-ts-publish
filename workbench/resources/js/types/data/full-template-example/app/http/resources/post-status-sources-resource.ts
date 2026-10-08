import { type AsEnum } from '@tolki/ts';

import { Status, Visibility } from '../../enums';

/**
 * Wraps the post's enums in an EnumResource reached through a local, a helper on the resource and a method that returns
 * the enum, which each publish the AsEnum type `EnumResource::make($this->status)` does.
 *
 * @see Workbench\App\Http\Resources\PostStatusSourcesResource
 */
export interface PostStatusSourcesResource
{
    status_from_local: AsEnum<typeof Status>;
    visibility_from_local: AsEnum<typeof Visibility> | null;
    status_from_helper: AsEnum<typeof Status>;
    visibility_from_helper: AsEnum<typeof Visibility> | null;
    status_from_method: AsEnum<typeof Status>;
}
