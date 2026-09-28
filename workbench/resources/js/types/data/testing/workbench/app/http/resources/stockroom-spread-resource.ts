import type { MenuSettingsType } from '@js/types/settings';
import type { User } from '../../models';

/**
 * Spreads `$this->only()` over two accessors that each name a model and a `#[TsType]` class.
 *
 * @see Workbench\App\Http\Resources\StockroomSpreadResource
 */
export interface StockroomSpreadResource
{
    contact: User | MenuSettingsType | null;
    layout: { manager: User | null; settings: MenuSettingsType | null };
    id: number;
}
