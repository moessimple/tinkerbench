<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\StringRule;

class SnippetNameRequest extends FormRequest
{
    /** @return array<string, list<string|StringRule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::string()->alphaDash(true)->max(200)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
