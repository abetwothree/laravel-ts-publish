/** @see Workbench\App\Events\HandoverListed */
export interface HandoverListed {
    stages: string[];
    ids: number[];
    codes: { "200": string; "404": string };
    override: { code: string; lane: number };
}
