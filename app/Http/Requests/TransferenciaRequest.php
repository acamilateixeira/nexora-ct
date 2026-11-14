<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Foundation\Http\FormRequest;

class TransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'not_regex:/[\r\n]/', 'max:255'],
            'valor' => ['required', 'string', 'max:20'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function valor(): Money
    {
        return Money::fromDecimalString((string) $this->input('valor'));
    }
}
