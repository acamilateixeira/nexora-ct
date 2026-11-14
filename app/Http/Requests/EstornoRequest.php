<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstornoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('estornar', $this->route('operacao')) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
