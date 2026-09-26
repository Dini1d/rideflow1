<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD']==='POST'){csrf_guard();$act=trim($_POST['action']??'');
  if($act==='delete') db_exec("DELETE FROM feedback WHERE id=?",[(int)($_POST['id']??0)]);
  if($act==='toggle') db_exec("UPDATE feedback SET is_public=1-is_public WHERE id=?",[(int)($_POST['id']??0)]);
  flash('ok','Done.'); redirect(SITE_URL.'/admin/feedback.php');}

$page   = max(1,(int)($_GET['page']??1));
$result = paginate("SELECT f.*,u.name uname,b.booking_ref FROM feedback f JOIN users u ON u.id=f.user_id LEFT JOIN bookings b ON b.id=f.booking_id ORDER BY f.created_at DESC", [], $page);
$avg    = db_val("SELECT AVG(rating) FROM feedback");
$total  = db_val("SELECT COUNT(*) FROM feedback");
$pageTitle='Feedback & Reviews';
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
<div class="page-hd"><h1>Feedback & Reviews</h1><p><?= $total ?> total reviews · Avg rating: <?= number_format(num_or_zero($avg),1) ?>/5 ⭐</p></div>
<?= flash_html() ?>
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem">
  <?php for($i=5;$i>=1;$i--): $cnt=db_val("SELECT COUNT(*) FROM feedback WHERE rating=?",[$i]); ?>
  <div class="card card-pad" style="flex:1;text-align:center">
    <div style="font-size:1.35rem;font-weight:900;color:var(--chalk)"><?= $cnt ?></div>
    <div style="color:var(--warn);font-size:.85rem"><?= str_repeat('★',$i).str_repeat('☆',5-$i) ?></div>
  </div>
  <?php endfor; ?>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Customer</th><th>Booking</th><th>Rating</th><th>Comment</th><th>Visibility</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(!$result['items']):?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--chalk4)">No reviews yet</td></tr><?php endif;?>
        <?php foreach($result['items'] as $f):?>
        <tr>
          <td style="font-weight:600;font-size:.87rem"><?= e($f['uname']) ?></td>
          <td><span class="mono" style="font-size:.78rem;color:var(--fire)"><?= e($f['booking_ref']??'—') ?></span></td>
          <td><span style="color:var(--warn)"><?= str_repeat('★',$f['rating']) ?></span><span style="color:var(--chalk4)"><?= str_repeat('★',5-$f['rating']) ?></span></td>
          <td style="max-width:280px;font-size:.84rem;color:var(--chalk3)"><?= $f['comment'] ? e(mb_substr($f['comment'],0,80)).(mb_strlen($f['comment'])>80?'…':'') : '<em style="color:var(--chalk4)">No comment</em>' ?></td>
          <td><?= $f['is_public'] ? '<span class="badge b-ok">Public</span>' : '<span class="badge b-dim">Hidden</span>' ?></td>
          <td style="font-size:.78rem;color:var(--chalk4)"><?= date('M j, Y',strtotime($f['created_at'])) ?></td>
          <td>
            <form method="POST" style="display:flex;gap:.3rem">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $f['id'] ?>">
              <button name="action" value="toggle" class="btn btn-dark btn-sm" title="Toggle visibility"><i class="fas fa-eye"></i></button>
              <button name="action" value="delete" class="btn btn-bad btn-sm" title="Delete" onclick="return confirm('Delete review?')"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php if($result['pages']>1):?><div class="pagination"><?php for($p=1;$p<=$result['pages'];$p++):?><a href="?page=<?= $p ?>" class="page-btn <?= $p===$page?'on':'' ?>"><?= $p ?></a><?php endfor;?></div><?php endif;?>
</div></div></div>
</body></html>
