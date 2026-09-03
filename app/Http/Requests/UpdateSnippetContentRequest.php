<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\StringRule;

class UpdateSnippetContentRequest extends FormRequest
{
    /** @return array<string, list<string|StringRule>> */
    public function rules(): array
    {
        // `present` + `nullable`, not `required`: emptying the editor is a normal editing state and
        // must save as an empty snippet. ConvertEmptyStringsToNull turns the cleared editor's ""
        // into null before validation, so `nullable` is what actually lets it through; content()
        // coalesces that null back to "".
        return [
            'content' => ['present', 'nullable', Rule::string()->max(100_000)],
        ];
    }

    public function content(): string
    {
        return $this->string('content')->toString();
    }
}
