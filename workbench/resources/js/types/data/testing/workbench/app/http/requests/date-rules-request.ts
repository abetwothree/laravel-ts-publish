/**
 * Get the validation rules that apply to the request.
 *
 * @see Workbench\App\Http\Requests\DateRulesRequest
 */
export interface DateRulesRequest {
    /** @format date */
    event_date: string;
    /** @format date */
    start_date: string;
    /** @format date */
    registration_deadline: string;
    /** @format date */
    birth_date: string;
    /** @format date */
    end_date: string;
    /** @format date */
    release_date: string;
    formatted_date: string;
    flexible_date: string;
    /** @format date */
    follow_up_date: string;
    /** @format date */
    cancelled_at?: string | null;
    user_timezone: string;
    us_timezone?: string | null;
}
