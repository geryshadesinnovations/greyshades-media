<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Csrf;

final class VerifyCsrf
{
    public function handle(): void
    {
        Csrf::verifyOrFail();
    }
}
