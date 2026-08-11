<?php

declare(strict_types=1);

namespace Application\Snippets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunSnippetRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100000'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
