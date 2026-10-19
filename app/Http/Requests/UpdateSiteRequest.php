<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

class UpdateSiteRequest extends StoreSiteRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('site'));
    }

    /**
     * Domains must be unique within the workspace, ignoring the site being updated.
     */
    protected function uniqueDomainRule(): Unique
    {
        return parent::uniqueDomainRule()->ignore($this->route('site'));
    }
}
