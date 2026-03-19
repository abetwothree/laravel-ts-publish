import { defineEnum } from '@tolki/enum';

export const ArticleStatus = defineEnum({
    Draft: 'draft',
    InReview: 'in_review',
    Published: 'published',
    Archived: 'archived',
    backed: true,
    _cases: ['Draft', 'InReview', 'Published', 'Archived'],
} as const);

export type ArticleStatusType = 'draft' | 'in_review' | 'published' | 'archived';

export type ArticleStatusKind = 'Draft' | 'InReview' | 'Published' | 'Archived';
