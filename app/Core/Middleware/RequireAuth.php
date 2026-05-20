<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;

final class RequireAuth
{
    public function handle(): void
    {
        Auth::requireLogin();
    }
}
