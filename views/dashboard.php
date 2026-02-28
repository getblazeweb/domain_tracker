<?php
declare(strict_types=1);
?>
<?php if (!empty($updateAvailable)): ?>
    <div class="alert alert-warn update-banner">
        Update available (<?php echo (int) ($updateAvailable['count'] ?? 0); ?> file<?php echo ((int) ($updateAvailable['count'] ?? 0) === 1) ? '' : 's'; ?>). 
        <a href="/updater.php" class="button">Open Updater</a>
    </div>
<?php endif; ?>

<?php if (!empty($expiryAlert) && (int) ($expiryAlert['count_7'] ?? 0) > 0): ?>
    <div class="alert alert-error update-banner">
        <?php echo (int) $expiryAlert['count_7']; ?> domain<?php echo (int) $expiryAlert['count_7'] === 1 ? '' : 's'; ?> expiring in 7 days.
        <a href="/index.php?action=expiry" class="button">View Expiry</a>
    </div>
<?php elseif (!empty($expiryAlert) && (int) ($expiryAlert['count_30_total'] ?? 0) > 0): ?>
    <div class="alert alert-warn update-banner">
        <?php echo (int) $expiryAlert['count_30_total']; ?> domain<?php echo (int) $expiryAlert['count_30_total'] === 1 ? '' : 's'; ?> expiring in 30 days.
        <a href="/index.php?action=expiry" class="button">View Expiry</a>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="muted">Track domains, subdomains, and database details in one place.</p>
    </div>
    <div class="header-actions">
        <form method="get" class="search-form" action="/index.php">
            <input type="text" name="q" placeholder="Search domains and subdomains" value="<?php echo e((string) ($search ?? '')); ?>">
            <?php if (!empty($search)): ?>
                <a class="button" href="/index.php">Clear</a>
            <?php endif; ?>
            <button type="submit" class="button primary">Search</button>
        </form>
        <a class="button" href="/index.php?action=domain_import">Import CSV</a>
        <a class="button primary" href="/index.php?action=domain_create">Add Domain</a>
    </div>
</div>

<?php if (empty($domains)): ?>
    <div class="empty-state">
        <p><?php echo empty($search) ? 'No domains yet. Add your first domain to get started.' : 'No results found for your search.'; ?></p>
    </div>
<?php endif; ?>

<?php foreach ($domains as $domain): ?>
    <?php $domainId = (int) $domain['id']; ?>
    <?php $subs = $subdomainsByDomain[$domainId] ?? []; ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h2><?php echo e((string) $domain['name']); ?></h2>
                <div class="badge"><?php echo count($subs); ?> Subdomain<?php echo count($subs) === 1 ? '' : 's'; ?></div>
                <?php if (!empty($domain['registrar'])): ?>
                    <span class="muted" style="font-size:12px;"><?php echo e((string) $domain['registrar']); ?></span>
                <?php endif; ?>
                <?php if (!empty($domain['expires_at'])): ?>
                    <span class="muted" style="font-size:12px;">Expires <?php echo e((string) $domain['expires_at']); ?></span>
                <?php endif; ?>
                <a class="link external-link" href="<?php echo e((string) $domain['url']); ?>" target="_blank" rel="noreferrer">
                    <?php echo e((string) $domain['url']); ?>
                </a>
                <?php if (!empty($domain['description'])): ?>
                    <p class="muted description"><?php echo e((string) $domain['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="status-column">
                <?php if (!empty($domain['db_host'])): ?>
                    <div class="status-line">DB Credentials Present</div>
                <?php endif; ?>
            </div>
            <div class="actions">
                <div class="actions-row">
                    <button type="button" class="button toggle-btn" data-target="domain-<?php echo $domainId; ?>" aria-expanded="false">
                        Expand
                    </button>
                    <a class="button" href="/index.php?action=subdomain_create&domain_id=<?php echo (int) $domain['id']; ?>">Add Subdomain</a>
                    <a class="button" href="/index.php?action=domain_edit&id=<?php echo (int) $domain['id']; ?>">Edit</a>
                    <form method="post" action="/index.php?action=domain_delete" onsubmit="return confirm('Delete this domain? Subdomains must be deleted first.');">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $domain['id']; ?>">
                        <button type="submit" class="button danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="collapsible-content is-collapsed" id="domain-<?php echo $domainId; ?>" hidden>
            <?php if (empty($subs)): ?>
                <div class="sub-empty">No subdomains for this domain.</div>
            <?php else: ?>
                <div class="subdomains">
                    <?php foreach ($subs as $sub): ?>
                        <?php $subId = (int) $sub['id']; ?>
                        <div class="sub-card">
                            <div class="sub-header">
                                <div>
                                    <h3><?php echo e((string) $sub['name']); ?></h3>
                                    <a class="link external-link" href="<?php echo e((string) $sub['url']); ?>" target="_blank" rel="noreferrer">
                                        <?php echo e((string) $sub['url']); ?>
                                    </a>
                                    <?php if (!empty($sub['description'])): ?>
                                        <p class="muted description"><?php echo e((string) $sub['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="status-column">
                                    <?php if (!empty($sub['db_host'])): ?>
                                        <div class="status-line">DB Credentials Present</div>
                                    <?php endif; ?>
                                </div>
                                <div class="actions">
                                    <div class="actions-row">
                                        <button type="button" class="button toggle-btn" data-target="sub-<?php echo $subId; ?>" aria-expanded="false">
                                            Expand
                                        </button>
                                        <a class="button" href="/index.php?action=subdomain_edit&id=<?php echo (int) $sub['id']; ?>">Edit</a>
                                        <form method="post" action="/index.php?action=subdomain_delete" onsubmit="return confirm('Delete this subdomain?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) $sub['id']; ?>">
                                            <button type="submit" class="button danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="collapsible-content is-collapsed" id="sub-<?php echo $subId; ?>" hidden>
                                <div class="sub-grid">
                                    <div>
                                        <div class="label">File location</div>
                                        <div class="value mono truncate" title="<?php echo e((string) $sub['file_location']); ?>">
                                            <?php echo e((string) $sub['file_location']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="label">Database</div>
                                        <div class="value mono"><?php echo e((string) ($sub['db_name'] !== '' ? $sub['db_name'] : 'Not set')); ?></div>
                                    </div>
                                    <div>
                                        <div class="label">Host</div>
                                        <div class="value mono">
                                            <?php
                                            $subHost = (string) $sub['db_host'];
                                            $subPort = (string) $sub['db_port'];
                                            echo $subHost !== '' ? e($subHost . ($subPort !== '' ? ':' . $subPort : '')) : 'Not set';
                                            ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="label">User</div>
                                        <div class="value mono"><?php echo e((string) ($sub['db_user'] !== '' ? $sub['db_user'] : 'Not set')); ?></div>
                                    </div>
                                    <div>
                                        <div class="label">Password</div>
                                        <div class="value mono">
                                        <?php if (!empty($sub['db_password_plain'])): ?>
                                            <span class="password-mask" data-password="<?php echo e((string) $sub['db_password_plain']); ?>">••••••••</span>
                                            <button type="button" class="button tiny reveal-btn">Show</button>
                                            <button type="button" class="button tiny copy-btn">Copy</button>
                                            <?php else: ?>
                                                <span class="muted">Not set</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>

<script>
document.querySelectorAll('.reveal-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var mask = btn.previousElementSibling;
        var isMasked = mask.textContent === '••••••••';
        if (isMasked) {
            mask.textContent = mask.getAttribute('data-password') || '';
            btn.textContent = 'Hide';
        } else {
            mask.textContent = '••••••••';
            btn.textContent = 'Show';
        }
    });
});

document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var mask = btn.previousElementSibling.previousElementSibling;
        if (!mask) {
            return;
        }
        var value = mask.getAttribute('data-password') || '';
        if (!value) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
            });
        } else {
            var temp = document.createElement('textarea');
            temp.value = value;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            btn.textContent = 'Copied';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
        }
    });
});

document.addEventListener('click', function (event) {
    var btn = event.target.closest('.toggle-btn');
    if (!btn) {
        return;
    }
    var targetId = btn.getAttribute('data-target');
    if (!targetId) {
        return;
    }
    var target = document.getElementById(targetId);
    if (!target) {
        return;
    }
    var isCollapsed = target.classList.toggle('is-collapsed');
    target.hidden = isCollapsed;
    btn.setAttribute('aria-expanded', String(!isCollapsed));
    btn.textContent = isCollapsed ? 'Expand' : 'Collapse';
});
</script>
