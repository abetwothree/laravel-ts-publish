import type { MenuSettingsType } from '@js/types/settings';

/**
 * Reads the `menu_config` accessor, typed by a `#[TsType(import:)]` class, under a key no accessor shares.
 *
 * @see Workbench\App\Http\Resources\WarehouseSettingsResource
 */
export interface WarehouseSettingsResource
{
    id: number;
    settings: MenuSettingsType | null;
}
