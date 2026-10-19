<?php

namespace App\Http\Requests;

use App\Support\DateRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

class AnalyticsRangeRequest extends FormRequest
{
    /**
     * The longest custom range, in days, that can be requested.
     */
    public const int MAX_CUSTOM_DAYS = 90;

    /**
     * Pages using this request authorize access themselves.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:24h,7d,30d,custom'],
            'from' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    /**
     * Limit custom ranges so a single request cannot scan unbounded history.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('range') !== 'custom' || $validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                $days = Date::parse($this->input('from'))->diffInDays(Date::parse($this->input('to'))) + 1;

                if ($days > self::MAX_CUSTOM_DAYS) {
                    $validator->errors()->add('to', __('Choose a range of at most :days days.', ['days' => self::MAX_CUSTOM_DAYS]));
                }
            },
        ];
    }

    /**
     * The validated range, defaulting to the last 7 days.
     */
    public function dateRange(): DateRange
    {
        $range = $this->validated('range') ?? '7d';

        if ($range === 'custom') {
            return DateRange::days(
                Date::parse($this->validated('from')),
                Date::parse($this->validated('to')),
            );
        }

        return DateRange::preset($range, Date::now());
    }
}
