import { type AsEnum } from '@tolki/enum';

import { ArticleStatus, ContentType } from '../enums';
import type { GeoPoint } from '@/types/geo';
import type { User } from '../models';

export interface ApiArticleResource
{
    morphValue: string;
    id: number;
    computed: string;
    date_val: string;
    custom_val: CustomObject;
    plain: unknown;
    basic: unknown;
    firstName: string;
    lastName: string;
    isActive: boolean;
    location: GeoPoint;
    flag?: string | null;
    extra: Record<string, unknown>;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    status: AsEnum<typeof ArticleStatus>;
    content_type: AsEnum<typeof ContentType>;
    is_featured: boolean;
    author?: User;
}
