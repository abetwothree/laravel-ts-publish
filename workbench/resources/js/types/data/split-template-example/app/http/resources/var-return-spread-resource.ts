/**
 * Fixture resource exercising variable-return trait method spreads.
 *
 * @see Workbench\App\Http\Resources\VarReturnSpreadResource
 */
export interface VarReturnSpreadResource
{
    id: number;
    baseKey: string;
    conditionalKey?: string;
    always: string;
    sometimes?: string;
    ifBranch?: string;
    elseifBranch?: string;
    elseBranch?: string;
    dynamic: string;
    conditionalBaseKey?: string;
    foundB?: boolean;
    foreachKey?: string;
    forKey?: string;
    whileKey?: string;
    doWhileKey: string;
    doWhileBranchKey?: string;
    doWhileAfterBreakKey?: string;
    doWhileAfterContinueKey?: string;
    doWhileUnderBranchKey?: string;
    status: string;
}
