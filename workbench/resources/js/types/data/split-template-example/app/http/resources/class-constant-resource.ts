import { type AsEnum } from '@tolki/ts';

import { Status } from '../../enums';
import type { OrderStatusType, StatusType } from '../../enums';

/**
 * Regression fixture for Task 17A: SomeClass::CONSTANT as a property value resolves to a real TS
 * type via reflection instead of unknown. Also pins that `Foo::class` and an enum case reached
 * through EnumResource::make() keep resolving exactly as before alongside the new feature.
 *
 * @see Workbench\App\Http\Resources\ClassConstantResource
 */
export interface ClassConstantResource
{
    owner_minimum_channels: { in_app: { status_updates: boolean; comments: boolean }; digest: { status_updates: boolean; comments: boolean } };
    max_retries: number;
    schema_version: number;
    base_version: number;
    default_status: StatusType;
    fallback_channels: { in_app: { status_updates: boolean; comments: boolean }; digest: { status_updates: boolean; comments: boolean } };
    channel_tags: string[];
    mixed_tags: (string | number)[];
    nested_tags: { primary: string[]; secondary: string[] };
    broken_channels: unknown;
    over_element_limit: number[];
    over_depth_limit: unknown;
    status_map: { status: OrderStatusType };
    status_list: OrderStatusType[];
    all_int_keys: string[];
    mixed_keys: { a: number; "5": string };
    resource_marker: string;
    status_marker: AsEnum<typeof Status>;
}
