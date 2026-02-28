<?php
declare(strict_types=1);

$overdue = $expiryData['overdue'] ?? [];
$within7 = $expiryData['within_7'] ?? [];
$within30 = $expiryData['within_30'] ?? [];
$within60 = $expiryData['within_60'] ?? [];
$within90 = $expiryData['within_90'] ?? [];
?>
<div class="page-header">
    <div>
        <h1>Expiry Dashboard</h1>
        <p class="muted">Domains expiring in the next 90 days.</p>
    </div>
    <div class="header-actions">
        <a class="button refresh-attention" href="/index.php?action=check_expiry">Refresh</a>
        <a class="button primary" href="/index.php">Dashboard</a>
    </div>
</div>

<?php if (empty($overdue) && empty($within7) && empty($within30) && empty($within60) && empty($within90)): ?>
    <div class="empty-state">
        <p>No domains with expiry dates in the next 90 days. Add expiry dates to your domains to see them here.</p>
    </div>
<?php else: ?>
    <?php
    $renderBucket = function (array $domains, string $title, string $cssClass) {
        if (empty($domains)) {
            return;
        }
        ?>
        <div class="card">
            <h2 class="<?php echo e($cssClass); ?>"><?php echo e($title); ?> (<?php echo count($domains); ?>)</h2>
            <div class="table expiry-table">
                <div class="table-row table-head">
                    <div>Domain</div>
                    <div>Registrar</div>
                    <div>Expires</div>
                    <div>Renewal</div>
                    <div>Auto-renew</div>
                    <div></div>
                </div>
                <?php foreach ($domains as $d): ?>
                    <div class="table-row">
                        <div>
                            <a class="link" href="/index.php?action=domain_edit&id=<?php echo (int) $d['id']; ?>"><?php echo e((string) $d['name']); ?></a>
                            <div class="value mono" style="font-size:12px;"><?php echo e((string) $d['url']); ?></div>
                        </div>
                        <div><?php echo e((string) ($d['registrar'] ?? '—')); ?></div>
                        <div><?php echo e((string) ($d['expires_at'] ?? '—')); ?></div>
                        <div><?php echo e((string) ($d['renewal_price'] ?? '—')); ?></div>
                        <div><?php echo ((string) ($d['auto_renew'] ?? '')) === '1' ? 'Yes' : (((string) ($d['auto_renew'] ?? '')) === '0' ? 'No' : '—'); ?></div>
                        <div>
                            <a class="button tiny" href="/index.php?action=domain_edit&id=<?php echo (int) $d['id']; ?>">Edit</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    };
    ?>

    <?php $renderBucket($overdue, 'Overdue', 'expiry-urgent'); ?>
    <?php $renderBucket($within7, 'Expiring in 7 days', 'expiry-urgent'); ?>
    <?php $renderBucket($within30, 'Expiring in 30 days', 'expiry-soon'); ?>
    <?php $renderBucket($within60, 'Expiring in 60 days', ''); ?>
    <?php $renderBucket($within90, 'Expiring in 90 days', ''); ?>
<?php endif; ?>
