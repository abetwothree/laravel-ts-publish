/** @see Workbench\App\Http\Requests\DateRulesRequest */
export interface DateRulesRequest {
    /** @format date-time */
    event_date: string;
    /** @format date-time */
    start_date: string;
    /** @format date-time */
    registration_deadline: string;
    /** @format date-time */
    birth_date: string;
    /** @format date-time */
    end_date: string;
    /** @format date-time */
    release_date: string;
    formatted_date: string;
    flexible_date: string;
    /** @format date-time */
    follow_up_date: string;
    /** @format date-time */
    cancelled_at?: string | null;
    user_timezone: string;
    us_timezone?: string | null;
}
