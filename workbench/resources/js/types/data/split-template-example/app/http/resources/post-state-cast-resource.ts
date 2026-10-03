import { type AsEnum } from '@tolki/ts';

import { Status, Visibility } from '../../enums';

/**
 * Lays a cast over each key that holds an enum resource: each key publishes its cast, and imports an enum only where
 * the cast writes its wrap.
 *
 * @see Workbench\App\Http\Resources\PostStateCastResource
 */
export interface PostStateCastResource
{
    id: number;
    status: string;
    visibility: string;
    either: string | null;
    held: string | null;
    wrapped: AsEnum<typeof Status> | AsEnum<typeof Visibility> | null;
}
