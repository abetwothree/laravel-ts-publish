import { defineEnum } from '@tolki/enum';

/** Enum with methods excluded via #[TsExclude] — tests method-level exclusion when auto_include is enabled and when explicit attributes are present. */
export const ExcludableEnum = defineEnum({
    Alpha: 'alpha',
    Beta: 'beta',
    _cases: ['Alpha', 'Beta'],
} as const);

export type ExcludableEnumType = 'alpha' | 'beta';

export type ExcludableEnumKind = 'Alpha' | 'Beta';
