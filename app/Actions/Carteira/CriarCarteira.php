<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Models\Carteira;
use App\Models\User;

class CriarCarteira
{
    public function handle(User $user): Carteira
    {
        return Carteira::create(['user_id' => $user->id]);
    }
}
