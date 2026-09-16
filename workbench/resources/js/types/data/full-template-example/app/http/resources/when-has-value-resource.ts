/**
 * Exercises whenHas()/whenAppended()/whenExistsLoaded() typing from the value Laravel actually
 * returns rather than from the named attribute: each one ends in `value($value, ...)`, so a
 * closure's own return is what the property carries. whenHas()/whenExistsLoaded() forward the
 * attribute into the closure's first parameter; whenAppended() forwards nothing.
 *
 * @see Workbench\App\Http\Resources\WhenHasValueResource
 */
export interface WhenHasValueResource
{
    has_title?: boolean;
    title_length?: number;
    title_passthrough?: string;
    appended_label?: string;
    comments_flag?: string;
    title_unresolvable?: string;
}
