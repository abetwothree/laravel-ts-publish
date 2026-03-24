/** Fixture resource exercising variable-return trait method spreads. */
export interface VarReturnSpreadResource
{
    id: number;
    baseKey: string;
    conditionalKey?: unknown;
    always: unknown;
    sometimes?: unknown;
    ifBranch?: unknown;
    elseifBranch?: unknown;
    elseBranch?: unknown;
    conditionalBaseKey?: unknown;
}
