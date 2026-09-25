import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '../../models';

/**
 * Filters itself through `$this->only()`, keeping two accessors that each name a model and a `#[TsType]` class.
 *
 * @see Workbench\App\Http\Resources\StockroomOnlyResource
 */
export interface StockroomOnlyResource
{
    id: number;
    contact: User | MenuSettingsType | null;
    layout: { manager: User | null; settings: MenuSettingsType | null };
}
