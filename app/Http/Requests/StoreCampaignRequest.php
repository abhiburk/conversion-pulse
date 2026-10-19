<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Site;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', Campaign::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_id' => [
                'required',
                'integer',
                Rule::exists(Site::class, 'id')->where('workspace_id', $this->user()->current_workspace_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_id.exists' => __('Choose one of your sites.'),
        ];
    }
}
