/**
 * @see Workbench\App\Http\Requests\BooleanRulesRequest
 */
export interface BooleanRulesRequest {
    terms_accepted?: boolean;
    newsletter_accepted?: boolean;
    is_active?: boolean;
    is_archived?: boolean | null;
    is_featured?: boolean;
    marketing_declined?: boolean;
    privacy_declined?: boolean;
}
