<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Category;
use App\Models\Media;
use App\Models\Occasion;
use App\Models\Section;
use App\Models\Tag;

final class DashboardController
{
    public function index(): void
    {
        $allowedSections = $this->allowedSectionCodes();

        $filters = [
            'section_code' => $_GET['section']  ?? null,
            'category_id'  => isset($_GET['category']) ? (int) $_GET['category'] : null,
            'occasion_id'  => isset($_GET['occasion']) ? (int) $_GET['occasion'] : null,
            'tag_id'       => isset($_GET['tag'])      ? (int) $_GET['tag']      : null,
            'media_type'   => $_GET['type']     ?? null,
            'q'            => $_GET['q']        ?? null,
            'featured'     => !empty($_GET['featured']),
        ];

        $sort    = (string) ($_GET['sort']     ?? 'newest');
        $page    = (int)    ($_GET['page']     ?? 1);
        $perPage = (int)    ($_GET['per_page'] ?? 24);

        $result = Media::search($filters, $allowedSections, $sort, $page, $perPage);

        $sections = array_filter(Section::all(), fn ($s) => in_array($s['code'], $allowedSections, true));
        $trees    = [];
        foreach ($sections as $s) $trees[$s['code']] = Category::tree((int) $s['id']);

        echo view('dashboard/index', [
            'sections'     => array_values($sections),
            'trees'        => $trees,
            'occasions'    => Occasion::groupedAll(),
            'tags'         => Tag::popular(40),
            'result'       => $result,
            'filters'      => $filters,
            'sort'         => $sort,
            'mediaTypes'   => ['video' => 'Videos', 'image' => 'Images', 'pdf' => 'PDFs', 'ppt' => 'Presentations'],
        ]);
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
