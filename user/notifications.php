<?php
define('RF_ROOT', dirname(__DIR__));
$pageTitle = 'Notifications';
require_once RF_ROOT.'/includes/header.php';
require_login();
$uid   = current_uid();
if (db_table_exists('notifications')) {
  db_exec("UPDATE notifications SET is_read=1 WHERE user_id=?",[$uid]);
  $notifs = db_all("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50",[$uid]);
} else {
  $notifs = [];
}
?>
<div class="main-content"><div class="container-sm">
<h1 style="margin-bottom:1.5rem">Notifications</h1>
<?php if(!$notifs):?>
<div class="card card-pad" style="text-align:center;padding:3rem;color:var(--chalk3)"><i class="fas fa-bell-slash" style="font-size:2.5rem;display:block;margin-bottom:.75rem;color:var(--chalk4)"></i>No notifications yet</div>
<?php else: foreach($notifs as $n): $colors=['booking'=>'fire','payment'=>'ok','system'=>'info','promo'=>'warn']; ?>
<div class="card card-pad" style="margin-bottom:.75rem;border-left:3px solid var(--<?= $colors[$n['type']]??'info' ?>)">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap">
    <div>
      <div style="font-weight:700;color:var(--chalk);margin-bottom:.3rem"><?= e($n['title']) ?></div>
      <div style="font-size:.85rem;color:var(--chalk3)"><?= e($n['message']) ?></div>
      <?php if($n['link']):?><a href="<?= e($n['link']) ?>" class="btn btn-ghost btn-sm" style="margin-top:.6rem"><i class="fas fa-arrow-right"></i> View</a><?php endif;?>
    </div>
    <div style="font-size:.75rem;color:var(--chalk4);white-space:nowrap"><?= date('M j, H:i',strtotime($n['created_at'])) ?></div>
  </div>
</div>
<?php endforeach; endif; ?>
</div></div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
