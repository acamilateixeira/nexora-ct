<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function nomeAbreviado(): string
    {
        $partes = preg_split('/\s+/', trim($this->name)) ?: [];
        $primeiro = $partes[0] ?? '';

        return count($partes) > 1 ? $primeiro . ' ' . mb_strtoupper(mb_substr((string) end($partes), 0, 1)) . '.' : $primeiro;
    }

    /** @return HasOne<Carteira, $this> */
    public function carteira(): HasOne
    {
        return $this->hasOne(Carteira::class);
    }

    public function carteiraOuFalha(): Carteira
    {
        return $this->carteira ?? throw (new ModelNotFoundException())->setModel(Carteira::class);
    }
}
