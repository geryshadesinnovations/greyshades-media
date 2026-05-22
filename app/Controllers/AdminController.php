<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Category;
use App\Models\Company;
use App\Models\Media;
use App\Models\Section;
use App\Models\User;

final class AdminController
{
    public function dashboard(): void
    {
        $stats = Media::statsOverview();
        $recent = Database::all(
            "SELECT a.*, u.name AS user_name FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 30"
        );
        $live = Database::all(
            "SELECT us.*, u.name, u.email, m.title AS media_title, m.uuid AS media_uuid
             FROM user_sessions us
             JOIN users u ON u.id = us.user_id
             LEFT JOIN media m ON m.id = us.current_media_id
             WHERE us.is_active = 1 AND us.last_activity_at > (NOW() - INTERVAL 15 MINUTE)
             ORDER BY us.last_activity_at DESC LIMIT 30"
        );
        echo view('admin/dashboard', [
            'stats'         => $stats,
            'recent'        => $recent,
            'live'          => $live,
            'topViewed'     => Media::topViewed(10),
            'topDownloaded' => Media::topDownloaded(10),
        ]);
    }

    // -------- USERS --------
    public function users(): void
    {
        echo view('admin/users', [
            'users' => User::all(),
            'roles' => User::roles(),
        ]);
    }

    public function userStore(): void
    {
        Csrf::verifyOrFail();
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email.'); redirect('/admin/users');
        }
        if (Database::scalar("SELECT 1 FROM users WHERE email = ?", [$email])) {
            flash('error', 'A user with this email already exists.'); redirect('/admin/users');
        }
        $id = User::create([
            'name'             => trim((string) ($_POST['name'] ?? '')),
            'email'            => $email,
            'password'         => (string) ($_POST['password'] ?? ''),
            'role_id'          => (int) ($_POST['role_id'] ?? 0),
            'can_graphics'     => !empty($_POST['can_graphics']),
            'can_events'       => !empty($_POST['can_events']),
            'can_upload'       => !empty($_POST['can_upload']),
            'can_edit'         => !empty($_POST['can_edit']),
            'can_delete'       => !empty($_POST['can_delete']),
            'can_download'     => !empty($_POST['can_download']),
            'can_manage_users' => !empty($_POST['can_manage_users']),
            'is_active'        => 1,
        ]);
        ActivityLog::record('user.create', 'user', $id);
        flash('success', 'User created.');
        redirect('/admin/users');
    }

    public function userUpdate(int $id): void
    {
        Csrf::verifyOrFail();
        if (!User::find($id)) { http_response_code(404); return; }
        User::update($id, [
            'name'             => $_POST['name'] ?? null,
            'email'            => $_POST['email'] ?? null,
            'role_id'          => (int) ($_POST['role_id'] ?? 0),
            'can_graphics'     => !empty($_POST['can_graphics']),
            'can_events'       => !empty($_POST['can_events']),
            'can_upload'       => !empty($_POST['can_upload']),
            'can_edit'         => !empty($_POST['can_edit']),
            'can_delete'       => !empty($_POST['can_delete']),
            'can_download'     => !empty($_POST['can_download']),
            'can_manage_users' => !empty($_POST['can_manage_users']),
            'is_active'        => !empty($_POST['is_active']),
            'password'         => $_POST['password'] ?? null,
        ]);
        ActivityLog::record('user.update', 'user', $id);
        flash('success', 'User updated.');
        redirect('/admin/users');
    }

    public function userDelete(int $id): void
    {
        Csrf::verifyOrFail();
        if ($id === Auth::id()) {
            flash('error', 'You cannot deactivate your own account.');
            redirect('/admin/users');
        }
        User::delete($id);
        ActivityLog::record('user.deactivate', 'user', $id);
        flash('success', 'User deactivated.');
        redirect('/admin/users');
    }

    // -------- CATEGORIES --------
    public function categories(): void
    {
        $sections = Section::all();
        $trees = [];
        foreach ($sections as $s) $trees[$s['code']] = Category::tree((int) $s['id']);
        echo view('admin/categories', [
            'sections' => $sections,
            'trees'    => $trees,
        ]);
    }

    public function categoryStore(): void
    {
        Csrf::verifyOrFail();
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $parentId  = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $name      = trim((string) ($_POST['name'] ?? ''));
        if ($sectionId === 0 || $name === '') {
            flash('error', 'Section and name are required.'); redirect('/admin/categories');
        }
        $id = Category::create($sectionId, $parentId, $name);
        ActivityLog::record('category.create', 'category', $id, ['name' => $name]);
        flash('success', 'Category created.');
        redirect('/admin/categories');
    }

    public function categoryDelete(int $id): void
    {
        Csrf::verifyOrFail();
        Category::delete($id);
        ActivityLog::record('category.delete', 'category', $id);
        flash('success', 'Category deleted.');
        redirect('/admin/categories');
    }

    // -------- ACTIVITY --------
    public function activity(): void
    {
        $rows = Database::all(
            "SELECT a.*, u.name AS user_name, u.email AS user_email
             FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 200"
        );
        echo view('admin/activity', ['rows' => $rows]);
    }

    // -------- COMPANIES --------
    public function companies(): void
    {
        echo view('admin/companies', [
            'companies' => Company::all(),
        ]);
    }

    public function companyStore(): void
    {
        Csrf::verifyOrFail();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Company name is required.');
            redirect('/admin/companies');
        }
        $id = Company::create($name);
        ActivityLog::record('company.create', 'company', $id, ['name' => $name]);
        flash('success', 'Company created.');
        redirect('/admin/companies');
    }

    public function companyDelete(int $id): void
    {
        Csrf::verifyOrFail();
        Company::delete($id);
        ActivityLog::record('company.delete', 'company', $id);
        flash('success', 'Company deleted.');
        redirect('/admin/companies');
    }
}
