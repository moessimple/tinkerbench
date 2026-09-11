<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunSnippetRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', Rule::string()->max(100_000)],
            'enabled_watchers' => ['sometimes', 'array'],
            'enabled_watchers.*' => [Rule::in(['view'])],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    /** @return list<string> */
    public function enabledWatchers(): array
    {
        return array_values(array_filter($this->array('enabled_watchers'), is_string(...)));
    }
}
