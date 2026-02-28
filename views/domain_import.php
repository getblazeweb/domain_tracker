<?php
declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <h1>Import Domains</h1>
        <p class="muted">Paste CSV data. First row must be headers. Supports domains and nested subdomains.</p>
    </div>
    <a class="button" href="/index.php">Back</a>
</div>

<div class="card">
    <p class="muted"><strong>Domains:</strong> <code>type</code>=domain (or omit), <code>name</code>, <code>url</code>. Optional: description, registrar, expires_at, renewal_price, auto_renew, db_host, db_port, db_name, db_user, db_password.</p>
    <p class="muted"><strong>Subdomains:</strong> <code>type</code>=subdomain, <code>parent_domain</code> (domain name or url), <code>name</code>, <code>url</code>, <code>file_location</code>. Optional: description, db_host, db_port, db_name, db_user, db_password.</p>
    <p class="muted">Put domain rows before their subdomains. Date format: YYYY-MM-DD. Auto-renew: 1/0 or yes/no.</p>

    <form method="post" class="form" action="/index.php?action=domain_import_process">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>
            CSV data
            <textarea name="csv_data" rows="14" placeholder="type,name,url,parent_domain,file_location,description,...&#10;domain,BlazeHost,https://blazehost.co,,,Main business domain,...&#10;subdomain,Analytics,https://analytics.blazehost.co,BlazeHost,/var/www/analytics,Web analytics,..."></textarea>
        </label>
        <div class="form-actions">
            <button type="submit" class="button primary">Import</button>
        </div>
    </form>
</div>
