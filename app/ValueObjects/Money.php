<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\Carteira\ValorInvalido;

final class Money
{
    /** @var int */
    private $cents;

    private function __construct(int $cents)
    {
        $this->cents = $cents;
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromDecimalString(string $value): self
    {
        $value = trim($value);

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $value) === 1) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d+(,\d{1,2})?$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            throw ValorInvalido::ilegivel();
        }

        $parts = explode('.', $value);
        $reais = ltrim($parts[0], '0');
        $centavos = str_pad($parts[1] ?? '', 2, '0');

        if (strlen($reais) > 9) {
            throw ValorInvalido::grandeDemais();
        }

        return new self(((int) $reais) * 100 + (int) $centavos);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(self $other): self
    {
        if ($other->cents > 0 && $this->cents > PHP_INT_MAX - $other->cents) {
            throw ValorInvalido::grandeDemais();
        }
        if ($other->cents < 0 && $this->cents < PHP_INT_MIN - $other->cents) {
            throw ValorInvalido::grandeDemais();
        }

        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return $this->add($other->negate());
    }

    public function negate(): self
    {
        if ($this->cents === PHP_INT_MIN) {
            throw ValorInvalido::grandeDemais();
        }

        return new self(-$this->cents);
    }

    public function isLessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function assertPositive(): self
    {
        if (! $this->isPositive()) {
            throw ValorInvalido::naoPositivo();
        }

        return $this;
    }

    public function format(): string
    {
        $digits = str_pad(ltrim((string) $this->cents, '-'), 3, '0', STR_PAD_LEFT);
        $reais = substr($digits, 0, -2);
        $centavos = substr($digits, -2);
        $reaisComMilhar = strrev(implode('.', str_split(strrev($reais), 3)));

        return ($this->isNegative() ? '-' : '') . 'R$ ' . $reaisComMilhar . ',' . $centavos;
    }

    public function toDecimalString(): string
    {
        $digits = str_pad(ltrim((string) $this->cents, '-'), 3, '0', STR_PAD_LEFT);

        return ($this->isNegative() ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
