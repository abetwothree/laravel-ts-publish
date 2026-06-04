/**
 * @see Workbench\App\Http\Requests\NumberRulesRequest
 */
export interface NumberRulesRequest {
    score: number;
    price: number;
    exchange_rate: number;
    sale_price: number;
    pin: number;
    verification_code: number;
    max_price: number;
    discounted_price: number;
    quantity: number;
    item_count: number;
    min_age: number;
    min_age_inclusive: number;
    max_age: number;
    retry_count: number;
    account_number: number;
    page: number;
    tracking_code: number;
    batch_size: number;
    amount: number;
    strict_amount: number;
    confirm_quantity: number;
    team_size: number;
}
