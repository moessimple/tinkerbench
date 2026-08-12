<?php

declare(strict_types=1);

namespace Application\Snippets\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\StringRule;

class RunSnippetRequest extends FormRequest
{
    /** @return array<string, list<string|StringRule>> */
    public function rules(): array
    {
        return [
            'code' => ['required', Rule::string()->max(100000)],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
