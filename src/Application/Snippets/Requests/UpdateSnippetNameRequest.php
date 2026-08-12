<?php

declare(strict_types=1);

namespace Application\Snippets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSnippetNameRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
