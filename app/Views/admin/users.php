<?php
/** @var array $users
 *  @var array $roles
 */
use App\Core\Csrf;
$this->extend('layouts/app');
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <div class="content-header"><h1>Users</h1></div>

        <details class="glass create-form">
            <summary>+ New user</summary>
            <form method="post" action="<?= url('/admin/users') ?>" class="grid-form">
                <?= Csrf::field() ?>
                <label><span>Name</span><input name="name" required></label>
                <label><span>Email</span><input type="email" name="email" required></label>
                <label><span>Password</span><input type="password" name="password" required minlength="8"></label>
                <label class="select"><span>Role</span>
                    <select name="role_id" required>
                        <option value="">— select —</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <fieldset class="perm-grid">
                    <legend>Permissions</legend>
                    <label><input type="checkbox" name="can_graphics" value="1"> Graphics access</label>
                    <label><input type="checkbox" name="can_events" value="1"> Events access</label>
                    <label><input type="checkbox" name="can_upload" value="1"> Upload</label>
                    <label><input type="checkbox" name="can_edit" value="1"> Edit</label>
                    <label><input type="checkbox" name="can_delete" value="1"> Delete</label>
                    <label><input type="checkbox" name="can_download" value="1"> Download</label>
                    <label><input type="checkbox" name="can_manage_users" value="1"> Manage users</label>
                </fieldset>
                <button type="submit" class="btn-primary">Create user</button>
            </form>
        </details>

        <table class="table glass">
            <thead><tr><th>User</th><th>Role</th><th>Sections</th><th>Permissions</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?= e($u['name']) ?></strong><br>
                        <small class="muted"><?= e($u['email']) ?></small>
                    </td>
                    <td><span class="badge soft"><?= e($u['role_name']) ?></span></td>
                    <td>
                        <?php if ($u['can_graphics']): ?><span class="badge tiny">Graphics</span><?php endif; ?>
                        <?php if ($u['can_events']): ?><span class="badge tiny">Events</span><?php endif; ?>
                    </td>
                    <td class="perm-pills">
                        <?php foreach (['can_upload'=>'Upload','can_edit'=>'Edit','can_delete'=>'Delete','can_download'=>'Download','can_manage_users'=>'Admin'] as $col=>$lbl): ?>
                            <?php if (!empty($u[$col])): ?><span class="badge tiny"><?= $lbl ?></span><?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                    <td><?= $u['is_active'] ? '<span class="dot ok"></span> Active' : '<span class="dot off"></span> Inactive' ?></td>
                    <td>
                        <details class="row-edit">
                            <summary>Edit</summary>
                            <form method="post" action="<?= url('/admin/users/' . (int)$u['id']) ?>" class="grid-form mini">
                                <?= Csrf::field() ?>
                                <label><span>Name</span><input name="name" value="<?= e($u['name']) ?>"></label>
                                <label><span>Email</span><input type="email" name="email" value="<?= e($u['email']) ?>"></label>
                                <label><span>New password</span><input type="password" name="password" placeholder="leave blank to keep"></label>
                                <label class="select"><span>Role</span>
                                    <select name="role_id">
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= (int)$r['id'] ?>" <?= $r['id'] == $u['role_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <fieldset class="perm-grid">
                                    <legend>Permissions</legend>
                                    <?php foreach (['can_graphics'=>'Graphics','can_events'=>'Events','can_upload'=>'Upload','can_edit'=>'Edit','can_delete'=>'Delete','can_download'=>'Download','can_manage_users'=>'Admin'] as $c=>$l): ?>
                                    <label><input type="checkbox" name="<?= $c ?>" value="1" <?= !empty($u[$c]) ? 'checked' : '' ?>> <?= $l ?></label>
                                    <?php endforeach; ?>
                                    <label><input type="checkbox" name="is_active" value="1" <?= !empty($u['is_active']) ? 'checked' : '' ?>> Active</label>
                                </fieldset>
                                <div class="row-actions">
                                    <button class="btn-primary" type="submit">Save</button>
                                </div>
                            </form>
                            <form method="post" action="<?= url('/admin/users/' . (int)$u['id'] . '/delete') ?>" onsubmit="return confirm('Deactivate this user?')" style="margin-top:.5rem">
                                <?= Csrf::field() ?>
                                <button class="btn-danger" type="submit">Deactivate</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
