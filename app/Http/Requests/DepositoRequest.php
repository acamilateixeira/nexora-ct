<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Foundation\Http\FormRequest;

class DepositoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'valor' => ['required', 'string', 'max:20'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function valor(): Money
    {
        return Money::fromDecimalString((string) $this->input('valor'));
    }
}
