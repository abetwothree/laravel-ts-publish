import { type AsEnum } from '@tolki/ts';

import { Status, Visibility } from '../../enums';

/**
 * Publishes a union of two enum resources and no enum resource of its own: the union brings the file's enum imports.
 *
 * @see Workbench\App\Http\Resources\PostStateResource
 */
export interface PostStateResource
{
    id: number;
    either: AsEnum<typeof Status> | AsEnum<typeof Visibility> | null;
}
