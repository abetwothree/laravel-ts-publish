/**
 * A local assigned from an `instanceof` ternary whose proven arm reads a relation only the subclass declares keeps the
 * narrowing for every read through it, as the same ternary written inline does. `$unproven` and `$negatedTrueArm`
 * read that relation in the arm the test does not prove, so nothing narrows them.
 *
 * @see Workbench\App\Http\Resources\TeamSubscriberLocalResource
 */
export interface TeamSubscriberLocalResource
{
    subscriber_name: string | null;
    subscriber_id: number | null;
    inline_name: string | null;
    negated_name: string | null;
    via_local_email: string | null;
    unproven_name: unknown;
    negated_true_arm_name: unknown;
}
