<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Validates the envelope of an ingestion request. Individual events are validated separately so that one malformed event does not reject the whole batch.
 */
class IngestEventsRequest extends FormRequest
{
    /**
     * The endpoint is public; the site key is checked by the controller.
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
            'site_key' => ['required', 'string', 'max:64'],
            'events' => ['required', 'array', 'list', 'max:'.config('tracking.ingest.max_batch_size')],
            'events.*' => ['required', 'array'],
        ];
    }

    /**
     * Reject oversized payloads and decode JSON bodies sent as text/plain.
     *
     * Browsers send the SDK's batches as text/plain (including via navigator.sendBeacon) so they qualify as simple CORS requests that skip a preflight round trip.
     */
    protected function prepareForValidation(): void
    {
        if (strlen($this->getContent()) > config('tracking.ingest.max_payload_bytes')) {
            throw new HttpException(413, __('The event payload is too large.'));
        }

        if ($this->isJson()) {
            return;
        }

        $decoded = json_decode($this->getContent(), true);

        if (is_array($decoded)) {
            $this->merge($decoded);
        }
    }
}
