<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_once RF_ROOT.'/mail/mailer.php';
require_admin();

$sent = 0; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body']    ?? '');
    if (!$subject || !$body) { $err = 'Subject and body are required.'; }
    else {
        $subs = db_all("SELECT email, name FROM subscriptions WHERE is_active=1");
        foreach ($subs as $sub) {
            $html = mail_template($subject, '<p>'.nl2br(htmlspecialchars($body)).'</p>
<hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:24px 0">
<p style="font-size:13px;color:#4a4740">You are receiving this because you subscribed to RideFlow newsletters.<br>
<a href="'.SITE_URL.'/unsubscribe.php?email='.urlencode($sub['email']).'" style="color:#4a4740">Unsubscribe</a></p>');
            if (mail_send_newsletter($sub['email'], $sub['name']??'Subscriber', $subject, $html)) $sent++;
        }
        flash('ok',"Newsletter sent to $sent subscribers.");
        redirect(SITE_URL.'/admin/newsletter.php');
    }
}

$subs  = db_all("SELECT * FROM subscriptions ORDER BY subscribed_at DESC LIMIT 50");
$total = db_val("SELECT COUNT(*) FROM subscriptions WHERE is_active=1");
$pageTitle = 'Newsletter';
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

<div class="page-hd"><h1><i class="fas fa-paper-plane" style="color:var(--fire)"></i> Newsletter</h1>
<p><?= $total ?> active subscribers</p></div>
<?= flash_html() ?>

<div class="grid-2" style="gap:1.5rem;align-items:start">
  <!-- Send form -->
  <div>
    <div class="card card-pad">
      <h3 style="font-size:1rem;margin-bottom:1rem">Send Newsletter</h3>
      <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-times-circle"></i> <?= e($err) ?></div><?php endif; ?>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="fld"><label>Subject Line</label>
          <input type="text" name="subject" class="fc" placeholder="RideFlow — New routes and offers!" required value="<?= e($_POST['subject']??'') ?>">
        </div>
        <div class="fld"><label>Message Body (plain text — rendered as HTML)</label>
          <textarea name="body" class="fc" rows="8" placeholder="Write your newsletter content here…" required><?= e($_POST['body']??'') ?></textarea>
        </div>
        <div class="alert a-info" style="margin-bottom:1rem"><i class="fas fa-info-circle"></i>
          This will send to <strong><?= $total ?></strong> active subscriber(s). Enable SMTP in Settings → General for real delivery. In demo mode it uses PHP mail().
        </div>
        <button type="submit" class="btn btn-fire" onclick="return confirm('Send to all <?= $total ?> subscribers?')">
          <i class="fas fa-paper-plane"></i> Send Newsletter
        </button>
      </form>
    </div>
  </div>

  <!-- Subscribers list -->
  <div>
    <div class="card">
      <div style="padding:.85rem 1.25rem;border-bottom:1px solid var(--rim);display:flex;justify-content:space-between;align-items:center">
        <h3 style="font-size:1rem;margin:0">Recent Subscribers</h3>
        <span style="font-size:.8rem;color:var(--chalk4)"><?= $total ?> active</span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Subscribed</th></tr></thead>
          <tbody>
            <?php if (!$subs): ?><tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--chalk4)">No subscribers yet</td></tr><?php endif; ?>
            <?php foreach ($subs as $s): ?>
            <tr>
              <td style="font-size:.84rem;color:var(--info)"><?= e($s['email']) ?></td>
              <td style="font-size:.84rem"><?= e($s['name']??'—') ?></td>
              <td><?= $s['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-dim">Unsub</span>' ?></td>
              <td style="font-size:.77rem;color:var(--chalk4)"><?= date('M j, Y',strtotime($s['subscribed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div></div></div>
</body></html>
