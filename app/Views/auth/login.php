<?php
/** @var string|null $error */
/** @var string $email */
$this->extend('layouts/auth');
?>
<section class="auth-card glass">
    <div class="auth-brand">
        <span class="brand-mark big">G</span>
        <h1>Greyshades</h1>
        <p>Innovations Pvt. Ltd. — Media Platform</p>
    </div>

    <?php if ($error): ?>
    <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('/login') ?>" class="auth-form">
        <?= \App\Core\Csrf::field() ?>
        <label>
            <span>Email address</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus placeholder="you@company.com">
        </label>
        <label class="password-field">
            <span>Password</span>
            <div class="input-with-icon">
                <input type="password" name="password" id="login-password" required autocomplete="current-password" placeholder="Enter your password">
                <button type="button" class="pw-toggle-btn" id="toggle-pw" aria-label="Show password">
                    <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
        </label>
        <button type="submit" class="btn-primary btn-block">Sign in</button>
        <p class="auth-hint">Authorised personnel only. All activity is monitored and logged.</p>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('toggle-pw');
    const input = document.getElementById('login-password');
    if (toggle && input) {
        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.querySelector('.eye-show').style.display = show ? 'none' : '';
            toggle.querySelector('.eye-hide').style.display = show ? '' : 'none';
        });
    }
});
</script>
