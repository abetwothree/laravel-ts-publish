import { defineEnum } from '@tolki/ts';

/**
 * Enum with methods excluded via #[TsExclude] — tests method-level exclusion when auto_include is enabled and when explicit attributes are present.
 *
 * @see Workbench\App\Enums\ExcludableEnum
 */
export const ExcludableEnum = defineEnum({
    Alpha: 'alpha',
    Beta: 'beta',
    backed: true,
    _cases: ['Alpha', 'Beta'],
} as const);

export type ExcludableEnumType = 'alpha' | 'beta';

export type ExcludableEnumKind = 'Alpha' | 'Beta';
