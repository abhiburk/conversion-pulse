<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreSiteRequest extends FormRequest
{
    /**
     * Matches a hostname such as example.com or localhost, with an optional port.
     */
    protected const string DOMAIN_PATTERN = '/^(localhost|([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,})(:\d{1,5})?$/';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', Site::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'regex:'.self::DOMAIN_PATTERN, $this->uniqueDomainRule()],
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
            'domain.regex' => __('Enter a valid domain, like example.com.'),
            'domain.unique' => __('This domain is already added to your workspace.'),
        ];
    }

    /**
     * Reduce the submitted domain to a bare, lowercase host so "https://Shop.example.com/pricing" becomes "shop.example.com".
     */
    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('domain'))) {
            return;
        }

        $this->merge([
            'domain' => Str::of($this->input('domain'))
                ->trim()
                ->lower()
                ->replaceMatches('#^[a-z][a-z0-9+.-]*://#', '')
                ->before('/')
                ->toString(),
        ]);
    }

    /**
     * Domains must be unique within the user's current workspace.
     */
    protected function uniqueDomainRule(): Unique
    {
        return Rule::unique(Site::class, 'domain')
            ->where('workspace_id', $this->user()->current_workspace_id);
    }
}
