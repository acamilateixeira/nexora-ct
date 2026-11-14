<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;

class GuestLayout extends Component
{
    /** @return \Illuminate\View\View */
    public function render()
    {
        return view('layouts.guest');
    }
}
