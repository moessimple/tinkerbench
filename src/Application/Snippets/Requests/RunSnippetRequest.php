<?php

declare(strict_types=1);

namespace Application\Snippets\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunSnippetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
