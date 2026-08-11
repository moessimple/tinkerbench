<?php

declare(strict_types=1);

namespace Application\Snippets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSnippetContentRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:100000'],
        ];
    }

    public function content(): string
    {
        return $this->string('content')->toString();
    }
}
