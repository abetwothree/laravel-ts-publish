import { defineEnum } from '@tolki/ts';

/**
 * Fixture: an enum with an override, which a value of the enum publishes, while its own file, an `EnumResource`, its
 * `->value` and its `->name` keep the enum's names.
 *
 * @see Workbench\App\Enums\UiTone
 */
export const UiTone = defineEnum({
    Bright: 'bright',
    Dim: 'dim',
    backed: true,
    _cases: ['Bright', 'Dim'],
} as const);

export type UiToneType = 'bright' | 'dim';

export type UiToneKind = 'Bright' | 'Dim';
