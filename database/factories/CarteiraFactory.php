<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Carteira;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Carteira> */
class CarteiraFactory extends Factory
{
    protected $model = Carteira::class;

    /** @return array<string, mixed> */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
        ];
    }
}
