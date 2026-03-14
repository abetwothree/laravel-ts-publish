import { defineEnum } from '@tolki/enum';

export const ContentType = defineEnum({
    Post: 'post',
    Tutorial: 'tutorial',
    Review: 'review',
    News: 'news',
    _cases: ['Post', 'Tutorial', 'Review', 'News'],
} as const);

export type ContentTypeType = 'post' | 'tutorial' | 'review' | 'news';

export type ContentTypeKind = 'Post' | 'Tutorial' | 'Review' | 'News';
