import { type AsEnum } from '@tolki/enum';

import { ArticleStatus, ContentType } from '../../enums';
import type { User } from '../../../app/models';

export interface ApiArticleResource
{
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    status: AsEnum<typeof ArticleStatus>;
    content_type: AsEnum<typeof ContentType>;
    is_featured: boolean;
    author?: User;
}
