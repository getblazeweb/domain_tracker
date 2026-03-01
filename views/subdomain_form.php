<?php
declare(strict_types=1);

$action = $mode === 'edit' ? 'subdomain_update' : 'subdomain_store';
?>
<div class="page-header">
    <div>
        <h1><?php echo $mode === 'edit' ? 'Edit Subdomain' : 'Add Subdomain'; ?></h1>
        <p class="muted">Subdomain details and database credentials.</p>
    </div>
    <a class="button" href="<?php echo e(app_url('index.php')); ?>">Back</a>
</div>

<form method="post" class="form card data-form" action="<?php echo e(app_url('index.php?action=' . $action)); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo (int) $subdomain['id']; ?>">
    <?php endif; ?>
    <input type="hidden" name="domain_id" value="<?php echo (int) $subdomain['domain_id']; ?>">

    <label>
        Subdomain Name
        <input type="text" name="name" value="<?php echo e((string) $subdomain['name']); ?>" required>
        <?php if (!empty($errors['name'])): ?>
            <span class="field-error"><?php echo e($errors['name']); ?></span>
        <?php endif; ?>
    </label>

    <label>
        Subdomain URL
        <input type="url" name="url" value="<?php echo e((string) $subdomain['url']); ?>" required>
        <?php if (!empty($errors['url'])): ?>
            <span class="field-error"><?php echo e($errors['url']); ?></span>
        <?php endif; ?>
    </label>

    <label>
        File Location
        <input type="text" name="file_location" value="<?php echo e((string) $subdomain['file_location']); ?>" required>
        <?php if (!empty($errors['file_location'])): ?>
            <span class="field-error"><?php echo e($errors['file_location']); ?></span>
        <?php endif; ?>
    </label>

    <label>
        Description
        <textarea name="description" rows="3"><?php echo e((string) ($subdomain['description'] ?? '')); ?></textarea>
    </label>

    <div class="form-grid">
    <label>
        DB Host
        <input type="text" name="db_host" value="<?php echo e((string) $subdomain['db_host']); ?>">
    </label>

    <label>
        DB Port
        <input type="text" name="db_port" value="<?php echo e((string) $subdomain['db_port']); ?>">
    </label>

    <label>
        DB Name
        <input type="text" name="db_name" value="<?php echo e((string) $subdomain['db_name']); ?>">
    </label>

    <label>
        DB User
        <input type="text" name="db_user" value="<?php echo e((string) $subdomain['db_user']); ?>">
    </label>
    </div>

    <label>
        DB Password
        <div class="password-field">
            <input type="password" name="db_password" value="<?php echo e((string) $subdomain['db_password']); ?>">
            <?php if ($mode === 'edit' && !empty($subdomain['db_password'])): ?>
                <button type="button" class="button tiny reveal-input-btn">Show</button>
                <button type="button" class="button tiny copy-input-btn">Copy</button>
            <?php endif; ?>
        </div>
        <?php if (!empty($errors['db_password'])): ?>
            <span class="field-error"><?php echo e($errors['db_password']); ?></span>
        <?php endif; ?>
        <?php if ($mode === 'edit'): ?>
            <span class="helper-text">Leave blank to keep the existing password.</span>
        <?php endif; ?>
    </label>

    <div class="form-actions">
        <button type="submit" class="button primary"><?php echo $mode === 'edit' ? 'Save Changes' : 'Create Subdomain'; ?></button>
    </div>
</form>

<script>
document.querySelectorAll('.reveal-input-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = btn.parentElement.querySelector('input');
        if (!input) {
            return;
        }
        var isPassword = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPassword ? 'text' : 'password');
        btn.textContent = isPassword ? 'Hide' : 'Show';
    });
});

document.querySelectorAll('.copy-input-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = btn.parentElement.querySelector('input');
        if (!input || !input.value) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(function () {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
            });
        } else {
            input.select();
            document.execCommand('copy');
            btn.textContent = 'Copied';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
        }
    });
});
</script>
