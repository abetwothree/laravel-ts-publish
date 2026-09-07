import { defineEnum } from '@tolki/ts';

/**
 * Fixture: a #[TsEnum(name:)] that differs from the class basename, reached from a page prop.
 *
 * @see Workbench\App\Enums\ShirtSize
 */
export const Size = defineEnum({
    Small: 's',
    Large: 'l',
    backed: true,
    _cases: ['Small', 'Large'],
} as const);

export type SizeType = 's' | 'l';

export type SizeKind = 'Small' | 'Large';
