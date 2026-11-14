<?php

declare(strict_types=1);

namespace App\Exceptions\Carteira;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

abstract class CarteiraException extends DomainException
{
    /** @var string */
    private $errorCode;

    /** @var string */
    private $field;

    /** @var array<string, mixed> */
    private $context;

    /** @param array<string, mixed> $context */
    final protected function __construct(string $message, string $errorCode, string $field = 'valor', array $context = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->field = $field;
        $this->context = $context;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function field(): string
    {
        return $this->field;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public function report(): void
    {
        Log::warning('carteira.' . $this->errorCode, $this->context);
    }

    /** @return JsonResponse|RedirectResponse */
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'erro' => ['codigo' => $this->errorCode, 'mensagem' => $this->getMessage()],
            ], 422);
        }

        return back()
            ->withInput($request->except(['password', 'password_confirmation']))
            ->withErrors([$this->field => $this->getMessage()]);
    }
}
