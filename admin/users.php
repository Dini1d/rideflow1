<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $uid = (int)($_POST['uid'] ?? 0);
    $act = trim($_POST['action'] ?? '');
    if ($uid && $act) {
        match ($act) {
            'activate'          => db_exec("UPDATE users SET status='active'    WHERE id=? AND role!='admin'", [$uid]),
            'suspend'           => db_exec("UPDATE users SET status='suspended' WHERE id=? AND role!='admin'", [$uid]),
            'deactivate'        => db_exec("UPDATE users SET status='inactive'  WHERE id=? AND role!='admin'", [$uid]),
            'approve_verification' => db_exec("UPDATE users SET verification_status='approved', status=CASE WHEN email_verified=1 THEN 'active' ELSE 'pending' END WHERE id=? AND role!='admin'", [$uid]),
            'reject_verification'  => db_exec("UPDATE users SET verification_status='rejected', status='inactive' WHERE id=? AND role!='admin'", [$uid]),
            'delete'            => db_exec("DELETE FROM users WHERE id=? AND role!='admin'", [$uid]),
            'promote_driver'    => db_exec("UPDATE users SET role='driver' WHERE id=?", [$uid]),
            default             => null
        };
        flash('ok', 'User updated.');
    }
    redirect(SITE_URL.'/admin/users.php');
}

$role   = trim($_GET['role']   ?? '');
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['q']      ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1']; $params = [];
if ($role)  { $where[] = 'u.role=?';   $params[] = $role;   }
if ($status){ $where[] = 'u.status=?'; $params[] = $status; }
if ($search){ $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$listSql = "SELECT u.*, COALESCE(b.bk_count,0) AS bk_count FROM users u"
     ." LEFT JOIN (SELECT user_id, COUNT(*) bk_count FROM bookings GROUP BY user_id) b ON b.user_id = u.id"
     ." WHERE ".implode(' AND ',$where)." ORDER BY u.created_at DESC";

$result = paginate($listSql, $params, $page);

$pageTitle = 'Manage Users';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head><body>
<div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto">
<?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">

<div class="page-hd">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
    <div><h1>Users</h1><p><?= number_format((int)num_or_zero($result['total'])) ?> total accounts</p></div>
  </div>
</div>

<?= flash_html() ?>

<!-- Filters -->
<div class="card card-pad" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:end">
    <div style="flex:1;min-width:180px">
      <div class="fw"><i class="fas fa-search fc-icon"></i>
        <input type="text" name="q" class="fc has-icon" placeholder="Search name, email, phone…" value="<?= e($search) ?>">
      </div>
    </div>
    <select name="role" class="fc" style="width:auto">
      <option value="">All Roles</option>
      <?php foreach (['customer','driver','admin'] as $r): ?>
      <option value="<?= $r ?>" <?= $role===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="fc" style="width:auto">
      <option value="">All Status</option>
      <?php foreach (['active','pending','suspended','inactive'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-fire btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<!-- Table -->
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th>User</th><th>Role</th><th>Phone</th><th>Bookings</th><th>Status</th><th>Joined</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!$result['items']): ?>
        <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--chalk4)">No users found</td></tr>
        <?php endif; ?>
        <?php foreach ($result['items'] as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.65rem">
              <div style="width:34px;height:34px;border-radius:50%;background:var(--fire-soft);color:var(--fire);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;flex-shrink:0">
                <?php if ($u['avatar_url']): ?>
                <img src="<?= e($u['avatar_url']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                <?php else: ?>
                <?= strtoupper(substr($u['name'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.88rem;color:var(--chalk)"><?= e($u['name']) ?></div>
                <div style="font-size:.75rem;color:var(--chalk4)"><?= e($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge <?= $u['role']==='admin'?'b-bad':($u['role']==='driver'?'b-info':'b-dim') ?>">
              <?= e($u['role']) ?>
            </span>
            <?php if ($u['social_provider']): ?>
            <span class="badge b-fire" style="font-size:.62rem"><?= e($u['social_provider']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:.85rem;color:var(--chalk3)"><?= e($u['phone'] ?: '—') ?></td>
          <td style="text-align:center;font-weight:700;color:var(--chalk)"><?= $u['bk_count'] ?></td>
          <td>
            <?= status_badge($u['status']) ?>
            <div style="margin-top:.35rem"><?= verification_badge($u['verification_status'] ?? 'pending') ?></div>
          </td>
          <td style="font-size:.78rem;color:var(--chalk3)"><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['role'] !== 'admin'): ?>
            <form method="POST" style="display:flex;gap:.3rem;flex-wrap:wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <?php if ($u['verification_status'] === 'pending'): ?>
              <button name="action" value="approve_verification" class="btn btn-ok btn-sm" title="Approve" onclick="return confirm('Approve verification for <?= e(addslashes($u['name'])) ?>?')"><i class="fas fa-check"></i></button>
              <button name="action" value="reject_verification" class="btn btn-bad btn-sm" title="Reject" onclick="return confirm('Reject verification for <?= e(addslashes($u['name'])) ?>?')"><i class="fas fa-times"></i></button>
              <?php endif; ?>
              <?php if ($u['status']==='active'): ?>
              <button name="action" value="suspend" class="btn btn-warn btn-sm" title="Suspend"><i class="fas fa-ban"></i></button>
              <?php else: ?>
              <button name="action" value="activate" class="btn btn-ok btn-sm" title="Activate" onclick="return confirm('Activate this user?')"><i class="fas fa-check"></i></button>
              <?php endif; ?>
              <?php if ($u['role']==='customer'): ?>
              <button name="action" value="promote_driver" class="btn btn-info btn-sm" title="Make Driver" onclick="return confirm('Promote to driver?')"><i class="fas fa-car"></i></button>
              <?php endif; ?>
              <button name="action" value="delete" class="btn btn-bad btn-sm" title="Delete" onclick="return confirm('Delete user <?= e(addslashes($u['name'])) ?>? This cannot be undone.')"><i class="fas fa-trash"></i></button>
            </form>
            <?php else: ?>
            <span style="font-size:.75rem;color:var(--chalk4)">Admin</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($result['pages'] > 1): ?>
<div class="pagination">
  <?php for ($p=1; $p<=$result['pages']; $p++): ?>
  <a href="?page=<?= $p ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>"
     class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

</div></div></div>
</body></html>
