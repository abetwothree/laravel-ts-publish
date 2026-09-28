import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '../../models';

/**
 * Filters its model through `$this->resource->only()`, keeping two accessors that each name a model and a `#[TsType]` class.
 *
 * @see Workbench\App\Http\Resources\StockroomPickResource
 */
export interface StockroomPickResource
{
    id: number;
    contact: User | MenuSettingsType | null;
    layout: { manager: User | null; settings: MenuSettingsType | null };
}
