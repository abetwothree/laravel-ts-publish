import { defineEnum } from '@tolki/ts';

/**
 * @see Workbench\Blog\Enums\ContentType
 */
export const ContentType = defineEnum({
    Post: 'post',
    Tutorial: 'tutorial',
    Review: 'review',
    News: 'news',
    backed: true,
    _cases: ['Post', 'Tutorial', 'Review', 'News'],
} as const);

export type ContentTypeType = 'post' | 'tutorial' | 'review' | 'news';

export type ContentTypeKind = 'Post' | 'Tutorial' | 'Review' | 'News';
