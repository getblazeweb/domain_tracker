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
        <input type="password" name="db_password" value="<?php echo e((string) ($domain['db_password'] ?? '')); ?>">
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
