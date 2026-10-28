import { type AsEnum } from '@tolki/ts';

import { UiTone } from '../../enums';
import type { UiNode, UiToneDto } from '@js/types/ui';
import type { ChartType, DialType } from '../../packages/ui';

/**
 * Fixture: values of overridden classes, through declared helpers and through `new`, so the file imports each override
 * and each composed class's `…Type` from its module; an overridden enum's case, and its `EnumResource`, which keeps the
 * enum's own name.
 *
 * @see Workbench\App\Http\Resources\UiPanelResource
 */
export interface UiPanelResource
{
    chart: ChartType;
    dial: DialType | null;
    panel: UiNode;
    panels: UiNode[];
    when_panel?: UiNode;
    maybe_chart: ChartType | null;
    tone: UiToneDto;
    tone_resource: AsEnum<typeof UiTone>;
}
