import { type AsEnum } from '@tolki/enum';

import { Priority, Status, Visibility } from '../../enums';

export interface PostResource
{
    morphValue: string;
    id: number;
    title: string;
    content: string;
    status: AsEnum<typeof Status>;
    visibility: AsEnum<typeof Visibility> | null;
    priority: AsEnum<typeof Priority> | null;
}
