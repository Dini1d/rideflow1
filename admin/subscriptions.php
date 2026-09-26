<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_guard();$act=trim($_POST['action']??'');
  if($act==='delete') db_exec("DELETE FROM subscriptions WHERE id=?",[(int)($_POST['id']??0)]);
  if($act==='toggle') db_exec("UPDATE subscriptions SET is_active=1-is_active WHERE id=?",[(int)($_POST['id']??0)]);
  redirect(SITE_URL.'/admin/subscriptions.php');}
$page=$result=null;$page=max(1,(int)($_GET['page']??1));
$result=paginate("SELECT * FROM subscriptions ORDER BY subscribed_at DESC",[],$page);
$pageTitle='Newsletter Subscriptions';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=20260923-professional">
</head><body><div class="admin-layout">
<?php require_once RF_ROOT.'/admin/admin_sidebar.php'; ?>
<div style="overflow-y:auto"><?php require_once RF_ROOT.'/admin/admin_topbar.php'; ?>
<div class="admin-main">
<div class="page-hd"><h1>Subscriptions</h1><p><?= number_format((int)num_or_zero($result['total'])) ?> subscribers</p></div>
<?= flash_html() ?>
<div class="card"><div class="table-wrap"><table class="table">
<thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Subscribed</th><th>Actions</th></tr></thead><tbody>
<?php if(!$result['items']):?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--chalk4)">No subscribers</td></tr><?php endif;?>
<?php foreach($result['items'] as $s):?>
<tr>
  <td style="color:var(--info);font-size:.86rem"><?= e($s['email']) ?></td>
  <td style="font-size:.86rem"><?= e($s['name']??'—') ?></td>
  <td><?= $s['is_active']?'<span class="badge b-ok">Active</span>':'<span class="badge b-dim">Unsub</span>' ?></td>
  <td style="font-size:.78rem;color:var(--chalk4)"><?= date('M j, Y',strtotime($s['subscribed_at'])) ?></td>
  <td><form method="POST" style="display:flex;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
    <button name="action" value="toggle" class="btn btn-dark btn-sm"><i class="fas fa-exchange-alt"></i></button>
    <button name="action" value="delete" class="btn btn-bad btn-sm" onclick="return confirm('Remove subscriber?')"><i class="fas fa-trash"></i></button>
  </form></td>
</tr>
<?php endforeach;?>
</tbody></table></div></div>
<?php if($result['pages']>1):?><div class="pagination"><?php for($p=1;$p<=$result['pages'];$p++):?><a href="?page=<?= $p ?>" class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a><?php endfor;?></div><?php endif;?>
</div></div></div></body></html>
