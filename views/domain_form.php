<?php
declare(strict_types=1);

$action = $mode === 'edit' ? 'domain_update' : 'domain_store';
?>
<div class="page-header">
    <div>
        <h1><?php echo $mode === 'edit' ? 'Edit Domain' : 'Add Domain'; ?></h1>
        <p class="muted">Domain details and optional database credentials.</p>
    </div>
    <a class="button" href="/index.php">Back</a>
</div>

<form method="post" class="form card data-form" action="/index.php?action=<?php echo e($action); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo (int) $domain['id']; ?>">
    <?php endif; ?>

    <label>
        Domain Name
        <input type="text" name="name" value="<?php echo e((string) $domain['name']); ?>" required>
        <?php if (!empty($errors['name'])): ?>
            <span class="field-error"><?php echo e($errors['name']); ?></span>
        <?php endif; ?>
    </label>

    <label>
        Domain URL
        <input type="url" name="url" value="<?php echo e((string) $domain['url']); ?>" required>
        <?php if (!empty($errors['url'])): ?>
            <span class="field-error"><?php echo e($errors['url']); ?></span>
        <?php endif; ?>
    </label>

    <label>
        Description
        <textarea name="description" rows="3"><?php echo e((string) ($domain['description'] ?? '')); ?></textarea>
    </label>

    <div class="form-grid">
        <label>
            Registrar
            <input type="text" name="registrar" value="<?php echo e((string) ($domain['registrar'] ?? '')); ?>" placeholder="e.g. Namecheap, Dynadot">
        </label>

        <label>
            Expires
            <input type="date" name="expires_at" value="<?php echo e((string) ($domain['expires_at'] ?? '')); ?>">
        </label>

        <label>
            Renewal Price
            <input type="text" name="renewal_price" value="<?php echo e((string) ($domain['renewal_price'] ?? '')); ?>" placeholder="e.g. 12.99">
        </label>

        <label>
            Auto-renew
            <select name="auto_renew">
                <option value="">—</option>
                <option value="1"<?php echo ((string) ($domain['auto_renew'] ?? '')) === '1' ? ' selected' : ''; ?>>Yes</option>
                <option value="0"<?php echo ((string) ($domain['auto_renew'] ?? '')) === '0' ? ' selected' : ''; ?>>No</option>
            </select>
        </label>
    </div>

    <div class="form-grid">
        <label>
            DB Host
            <input type="text" name="db_host" value="<?php echo e((string) ($domain['db_host'] ?? '')); ?>">
        </label>

        <label>
            DB Port
            <input type="text" name="db_port" value="<?php echo e((string) ($domain['db_port'] ?? '')); ?>">
        </label>

        <label>
            DB Name
            <input type="text" name="db_name" value="<?php echo e((string) ($domain['db_name'] ?? '')); ?>">
        </label>

        <label>
            DB User
            <input type="text" name="db_user" value="<?php echo e((string) ($domain['db_user'] ?? '')); ?>">
        </label>
    </div>

    <label>
        DB Password
        <div class="password-field">
            <input type="password" name="db_password" value="<?php echo e((string) ($domain['db_password'] ?? '')); ?>">
            <?php if ($mode === 'edit' && !empty($domain['db_password'])): ?>
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
        <button type="submit" class="button primary"><?php echo $mode === 'edit' ? 'Save Changes' : 'Create Domain'; ?></button>
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
