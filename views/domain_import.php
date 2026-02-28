<?php
declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <h1>Import Domains</h1>
        <p class="muted">Paste CSV data or upload a file. First row must be headers. Required: <code>name</code>, <code>url</code>.</p>
    </div>
    <a class="button" href="/index.php">Back</a>
</div>

<div class="card">
    <p class="muted">Column order (case-insensitive): <code>name</code>, <code>url</code>, <code>description</code>, <code>registrar</code>, <code>expires_at</code>, <code>renewal_price</code>, <code>auto_renew</code>, <code>db_host</code>, <code>db_port</code>, <code>db_name</code>, <code>db_user</code>, <code>db_password</code></p>
    <p class="muted">Date format: YYYY-MM-DD. Auto-renew: 1/0 or yes/no.</p>

    <form method="post" class="form" action="/index.php?action=domain_import_process">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>
            CSV data
            <textarea name="csv_data" rows="12" placeholder="name,url,description,registrar,expires_at,renewal_price,auto_renew,db_host,db_port,db_name,db_user,db_password&#10;example.com,https://example.com,My site,Namecheap,2025-12-31,12.99,1,localhost,3306,mydb,root,"></textarea>
        </label>
        <div class="form-actions">
            <button type="submit" class="button primary">Import</button>
        </div>
    </form>
</div>
