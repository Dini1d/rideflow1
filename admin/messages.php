<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $act = trim($_POST['action'] ?? '');
    if ($act === 'mark_read') {
        db_exec("UPDATE contact_messages SET is_read=1 WHERE id=?", [(int)($_POST['id']??0)]);
    }
    if ($act === 'delete_msg') {
        db_exec("DELETE FROM contact_messages WHERE id=?", [(int)($_POST['id']??0)]);
        flash('ok','Message deleted.');
    }
    redirect(SITE_URL.'/admin/messages.php');
}

$page   = max(1,(int)($_GET['page']??1));
$unread = (int)($_GET['filter']??'') === 0 ? '' : ($_GET['filter'] ?? '');
$where  = ['1=1']; $params = [];
if ($unread !== '') { $where[] = 'is_read=0'; }

$result = paginate("SELECT * FROM contact_messages WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC", $params, $page);
$pageTitle = 'Contact Messages';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head><body>
<div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto">
<?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">

<div class="page-hd">
  <h1>Contact Messages</h1>
  <p><?= number_format((int)num_or_zero($result['total'])) ?> total messages</p>
</div>
<?= flash_html() ?>

<div style="display:flex;gap:.5rem;margin-bottom:1.25rem">
  <a href="?" class="btn btn-<?= $unread===''?'fire':'dark' ?> btn-sm">All</a>
  <a href="?filter=0" class="btn btn-<?= $unread!==''?'fire':'dark' ?> btn-sm">Unread</a>
</div>

<?php if (!$result['items']): ?>
<div class="card card-pad" style="text-align:center;padding:2.5rem;color:var(--chalk4)"><i class="fas fa-inbox" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>No messages</div>
<?php endif; ?>

<?php foreach ($result['items'] as $m): ?>
<div class="card card-pad" style="margin-bottom:.85rem;border-left:3px solid var(--<?= $m['is_read']?'rim':'fire' ?>)">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem;flex-wrap:wrap">
        <strong style="color:var(--chalk)"><?= e($m['name']) ?></strong>
        <a href="mailto:<?= e($m['email']) ?>" style="font-size:.82rem;color:var(--info)"><?= e($m['email']) ?></a>
        <?php if (!$m['is_read']): ?><span class="badge b-fire" style="font-size:.65rem">NEW</span><?php endif; ?>
        <span style="font-size:.75rem;color:var(--chalk4)"><?= date('M j, Y H:i',strtotime($m['created_at'])) ?></span>
      </div>
      <div style="font-size:.9rem;font-weight:600;color:var(--chalk2);margin-bottom:.4rem"><?= e($m['subject']) ?></div>
      <div style="font-size:.85rem;color:var(--chalk3);line-height:1.6"><?= e($m['message']) ?></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.3rem;flex-shrink:0">
      <?php if (!$m['is_read']): ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button name="action" value="mark_read" class="btn btn-ok btn-sm"><i class="fas fa-check"></i> Read</button>
      </form>
      <?php endif; ?>
      <a href="mailto:<?= e($m['email']) ?>?subject=RE: <?= e(urlencode($m['subject'])) ?>" class="btn btn-info btn-sm"><i class="fas fa-reply"></i> Reply</a>
      <form method="POST" onsubmit="return confirm('Delete this message?')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button name="action" value="delete_msg" class="btn btn-bad btn-sm"><i class="fas fa-trash"></i></button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if ($result['pages'] > 1): ?>
<div class="pagination">
  <?php for ($p=1;$p<=$result['pages'];$p++): ?>
  <a href="?page=<?= $p ?>" class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

</div></div></div>
</body></html>
