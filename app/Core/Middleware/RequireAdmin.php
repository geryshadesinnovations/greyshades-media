<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;

final class RequireAdmin
{
    public function handle(): void
    {
        Auth::requireLogin();
        if (!Auth::isSuperAdmin() && !Auth::canManageUsers()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}
