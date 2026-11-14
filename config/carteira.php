<?php

declare(strict_types=1);

return [
    'atendimento' => [
        'telefone' => env('ATENDIMENTO_TELEFONE', '0800 000 0000'),
        'email' => env('ATENDIMENTO_EMAIL', 'atendimento@exemplo.com'),
        'horario' => env('ATENDIMENTO_HORARIO', 'segunda a sexta, das 8h às 20h'),
    ],
];
