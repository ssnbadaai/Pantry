<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

if (($_GET['action'] ?? '') === 'export') {
    $status = $_GET['status'] ?? 'all';
    $rows = $status === 'all'
        ? db_all('select email,first_name,last_name,status,subscribed_at,unsubscribed_at,source from subscribers order by email')
        : db_all('select email,first_name,last_name,status,subscribed_at,unsubscribed_at,source from subscribers where status=? order by email', [$status]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=subscribers-' . $status . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email','first_name','last_name','status','subscribed_at','unsubscribed_at','source']);
    foreach ($rows as $row) fputcsv($out, $row);
    exit;
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $email = strtolower(trim((string) $_POST['email']));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            db()->prepare(
                'insert into subscribers (email,first_name,last_name,status,subscription_token,unsubscribe_token,source,subscribed_at,created_at,updated_at)
                 values (?,?,?,?,?,?,?,now(),now(),now())
                 on duplicate key update first_name=values(first_name), last_name=values(last_name), status="active", unsubscribed_at=null, updated_at=now()'
            )->execute([$email, trim((string) $_POST['first_name']), trim((string) $_POST['last_name']), 'active', random_token(16), random_token(16), 'admin']);
        }
    }
    if ($action === 'unsubscribe') {
        db()->prepare('update subscribers set status="unsubscribed", unsubscribed_at=now(), updated_at=now() where id=?')->execute([(int) $_POST['id']]);
    }
    if ($action === 'delete') {
        db()->prepare('delete from subscribers where id=?')->execute([(int) $_POST['id']]);
    }
    if ($action === 'import' && !empty($_FILES['csv']['tmp_name'])) {
        $imported = $duplicates = $invalid = 0;
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map('strtolower', $header);
        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row) ?: [];
            $email = strtolower(trim((string) ($record['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;
                continue;
            }
            $exists = db_row('select id from subscribers where email=?', [$email]);
            if ($exists) {
                $duplicates++;
                continue;
            }
            db()->prepare('insert into subscribers (email,first_name,last_name,status,subscription_token,unsubscribe_token,source,subscribed_at,created_at,updated_at) values (?,?,?,?,?,?,?,now(),now(),now())')
                ->execute([$email, trim((string) ($record['first_name'] ?? '')), trim((string) ($record['last_name'] ?? '')), 'active', random_token(16), random_token(16), 'csv']);
            $imported++;
        }
        $notice = "$imported imported, $duplicates duplicates, $invalid invalid.";
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(email like ? or first_name like ? or last_name like ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
$sql = 'select * from subscribers' . ($where ? ' where ' . implode(' and ', $where) : '') . ' order by created_at desc limit 300';
$items = db_all($sql, $params);
admin_header('Subscribers', 'subscribers');
?>
<?php if ($notice): ?><div class="alert alert-info"><?= h($notice) ?></div><?php endif; ?>
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="panel h-100">
            <h2 class="h6">Add subscriber</h2>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-md-5"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
                <div class="col-md-3"><input class="form-control" name="first_name" placeholder="First name"></div>
                <div class="col-md-3"><input class="form-control" name="last_name" placeholder="Last name"></div>
                <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel h-100">
            <h2 class="h6">Import CSV</h2>
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import">
                <input class="form-control" name="csv" type="file" accept=".csv,text/csv" required>
                <button class="btn btn-outline-primary">Import</button>
            </form>
        </div>
    </div>
</div>
<form class="d-flex gap-2 mb-3">
    <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search subscribers">
    <select class="form-select" name="status" style="max-width:180px">
        <option value="">All statuses</option>
        <?php foreach (['active','pending','unsubscribed','bounced','blocked'] as $s): ?><option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= h(app_url('admin/subscribers.php?action=export&status=all')) ?>">Export All</a>
    <a class="btn btn-outline-secondary" href="<?= h(app_url('admin/subscribers.php?action=export&status=active')) ?>">Export Active</a>
</form>
<div class="table-wrap">
    <table class="table align-middle">
        <thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Subscribed</th><th>Source</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h($item['email']) ?></td>
                <td><?= h(trim($item['first_name'] . ' ' . $item['last_name'])) ?></td>
                <td><span class="status-pill"><?= h(ucfirst($item['status'])) ?></span></td>
                <td><?= h($item['subscribed_at']) ?></td>
                <td><?= h($item['source']) ?></td>
                <td class="text-end">
                    <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-outline-secondary" name="action" value="unsubscribe">Unsubscribe</button></form>
                    <form class="d-inline" method="post" onsubmit="return confirm('Delete this subscriber?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="action" value="delete">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" class="text-muted">No subscribers found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
