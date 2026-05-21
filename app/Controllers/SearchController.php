<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Media;

/**
 * Lightweight JSON endpoints that power the topbar search autocomplete.
 *
 * Suggestions span four buckets — media titles, tags, categories and
 * occasions — and respect the visiting user's section permissions
 * (graphics / events) so an Events-only user never sees Graphics media
 * leak through the autocomplete.
 */
final class SearchController
{
    public function suggest(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '' || mb_strlen($q) < 2) {
            echo json_encode(['ok' => true, 'q' => $q, 'items' => []]);
            return;
        }

        $allowed = $this->allowedSectionCodes();
        $items   = Media::suggest($q, $allowed, 10);

        echo json_encode(['ok' => true, 'q' => $q, 'items' => $items]);
    }

    /** @return array<int,string> */
    private function allowedSectionCodes(): array
    {
        if (Auth::isSuperAdmin()) return ['graphics', 'events'];
        $allowed = [];
        if (Auth::canSection('graphics')) $allowed[] = 'graphics';
        if (Auth::canSection('events'))   $allowed[] = 'events';
        return $allowed;
    }
}
