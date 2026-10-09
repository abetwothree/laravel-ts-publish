import { type AsEnum } from '@tolki/ts';

import { Status } from '../../enums';
import type { PageMetaType } from '@js/types/page-meta';
import type { MenuSettingsType } from '@js/types/settings';
import type { WidgetConfigType } from '@js/types/widget-config';
import type { PriorityType, StatusType } from '../../enums';
import type { Order } from '../../models';
import type { OrderItemResource } from '.';

/**
 * Exercises static-call return type reflection and enum static args (Task 10).
 *
 * @see Workbench\App\Http\Resources\StaticCallResource
 */
export interface StaticCallResource
{
    url: string;
    status_badge: AsEnum<typeof Status>;
    status_const: AsEnum<typeof Status>;
    items: OrderItemResource[];
    default_status: StatusType;
    located_order: Order;
    new_items: OrderItemResource[];
    menu_settings: MenuSettingsType;
    status_or_priority: StatusType | PriorityType;
    void_return: unknown;
    never_return: unknown;
    mixed_return: unknown;
    order_or_status: Order | StatusType;
    money_value: unknown;
    page_meta_ternary: PageMetaType | null;
    widget_config_coalesce: WidgetConfigType;
    order_or_base: Order;
    autocomplete: { value: number; label: string };
    summaries: { key: string; label: string }[];
}
