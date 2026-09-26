<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_admin();

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $q = trim($_POST['question'] ?? '');
        $a = trim($_POST['answer']   ?? '');
        $k = trim($_POST['keywords'] ?? '');
        $c = trim($_POST['category'] ?? '');
        if ($q && $a) {
            db_insert("INSERT INTO chatbot_faq(question,answer,keywords,category) VALUES(?,?,?,?)", [$q,$a,$k,$c]);
            flash('ok','FAQ added.');
        } else { flash('bad','Question and answer are required.'); }
    }
    if ($act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec("UPDATE chatbot_faq SET question=?,answer=?,keywords=?,category=?,is_active=? WHERE id=?",
            [trim($_POST['question']??''), trim($_POST['answer']??''), trim($_POST['keywords']??''),
             trim($_POST['category']??''), isset($_POST['is_active'])?1:0, $id]);
        flash('ok','FAQ updated.');
    }
    if ($act === 'delete') {
        db_exec("DELETE FROM chatbot_faq WHERE id=?", [(int)($_POST['id']??0)]);
        flash('ok','FAQ deleted.');
    }
    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec("UPDATE chatbot_faq SET is_active=1-is_active WHERE id=?", [$id]);
    }
    redirect(SITE_URL.'/admin/chatbot.php');
}

$faqs = db_all("SELECT * FROM chatbot_faq ORDER BY sort_order, id");
$stats = [
    'total'   => db_val("SELECT COUNT(*) FROM chatbot_messages"),
    'today'   => db_val("SELECT COUNT(*) FROM chatbot_messages WHERE DATE(created_at)=CURRENT_DATE"),
    'ai_msgs' => db_val("SELECT COUNT(*) FROM chatbot_messages WHERE role='user'"),
];

$pageTitle = 'Chatbot FAQ Manager';
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
  <h1><i class="fas fa-robot" style="color:var(--fire)"></i> Chatbot FAQ Manager</h1>
  <p>Manage rule-based answers. The AI chatbot checks FAQs first, then uses Claude AI as fallback.</p>
</div>

<?= flash_html() ?>

<!-- Stats -->
<div class="grid-3" style="margin-bottom:1.5rem">
  <div class="stat-card"><div class="stat-ico" style="background:var(--fire-soft);color:var(--fire)"><i class="fas fa-robot"></i></div>
    <div><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-lbl">Total Messages</div></div></div>
  <div class="stat-card"><div class="stat-ico" style="background:rgba(34,197,94,.1);color:var(--ok)"><i class="fas fa-calendar"></i></div>
    <div><div class="stat-num"><?= $stats['today'] ?></div><div class="stat-lbl">Today's Chats</div></div></div>
  <div class="stat-card"><div class="stat-ico" style="background:rgba(56,189,248,.1);color:var(--info)"><i class="fas fa-question-circle"></i></div>
    <div><div class="stat-num"><?= count($faqs) ?></div><div class="stat-lbl">Active FAQs</div></div></div>
</div>

<!-- Add FAQ -->
<div class="card card-pad" style="margin-bottom:1.5rem">
  <h3 style="font-size:1rem;margin-bottom:1rem"><i class="fas fa-plus" style="color:var(--fire)"></i> Add FAQ</h3>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field-group" style="margin-bottom:.75rem">
      <div class="fld" style="margin:0">
        <label>Question</label>
        <input type="text" name="question" class="fc" placeholder="How do I cancel my booking?" required>
      </div>
      <div class="fld" style="margin:0">
        <label>Category</label>
        <select name="category" class="fc">
          <option value="">Select…</option>
          <?php foreach (['booking','payment','routes','travel','support'] as $cat): ?>
          <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="fld">
      <label>Answer</label>
      <textarea name="answer" class="fc" rows="3" placeholder="Provide a clear, helpful answer…" required></textarea>
    </div>
    <div class="fld">
      <label>Keywords (comma-separated trigger words)</label>
      <input type="text" name="keywords" class="fc" placeholder="cancel, refund, cancellation">
      <div class="form-hint">The chatbot will match these keywords in user messages to show this answer.</div>
    </div>
    <button type="submit" class="btn btn-fire"><i class="fas fa-plus"></i> Add FAQ</button>
  </form>
</div>

<!-- FAQ List -->
<div class="card">
  <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--rim)"><h3 style="font-size:1rem;margin:0">FAQ Entries (<?= count($faqs) ?>)</h3></div>
  <?php if (!$faqs): ?>
  <div style="padding:2.5rem;text-align:center;color:var(--chalk4)">No FAQs yet. Add your first one above.</div>
  <?php endif; ?>
  <?php foreach ($faqs as $f): ?>
  <div style="border-bottom:1px solid var(--rim);padding:1rem 1.25rem" id="faq<?= $f['id'] ?>">
    <div style="display:flex;align-items:flex-start;gap:1rem">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;flex-wrap:wrap">
          <span style="font-weight:700;color:var(--chalk);font-size:.92rem"><?= e($f['question']) ?></span>
          <?php if ($f['category']): ?><span class="badge b-info"><?= e($f['category']) ?></span><?php endif; ?>
          <?php echo $f['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-dim">Inactive</span>'; ?>
        </div>
        <div style="font-size:.84rem;color:var(--chalk3);line-height:1.6;margin-bottom:.4rem"><?= e($f['answer']) ?></div>
        <?php if ($f['keywords']): ?>
        <div style="font-size:.75rem;color:var(--chalk4)"><i class="fas fa-key"></i> <?= e($f['keywords']) ?></div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:.4rem;flex-shrink:0">
        <button onclick="editFaq(<?= $f['id'] ?>,<?= htmlspecialchars(json_encode($f),ENT_QUOTES) ?>)" class="btn btn-dark btn-sm"><i class="fas fa-edit"></i></button>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <button type="submit" class="btn btn-dark btn-sm" title="Toggle active"><i class="fas fa-<?= $f['is_active']?'eye-slash':'eye' ?>"></i></button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this FAQ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <button type="submit" class="btn btn-bad btn-sm"><i class="fas fa-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

</div></div></div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1100;align-items:center;justify-content:center">
  <div style="background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r4);padding:1.5rem;width:90%;max-width:560px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h3 style="font-size:1rem;margin:0">Edit FAQ</h3>
      <button onclick="document.getElementById('editModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--chalk3);font-size:1.1rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" id="editForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="editId">
      <div class="fld"><label>Question</label><input type="text" name="question" id="editQ" class="fc" required></div>
      <div class="fld"><label>Answer</label><textarea name="answer" id="editA" class="fc" rows="4" required></textarea></div>
      <div class="fld"><label>Keywords</label><input type="text" name="keywords" id="editK" class="fc"></div>
      <div class="fld"><label>Category</label><input type="text" name="category" id="editC" class="fc"></div>
      <div style="margin-bottom:1rem"><label style="display:flex;align-items:center;gap:.5rem;cursor:pointer"><input type="checkbox" name="is_active" id="editActive" style="accent-color:var(--fire)"> Active</label></div>
      <button type="submit" class="btn btn-fire">Save Changes</button>
    </form>
  </div>
</div>

<script>
function editFaq(id, data) {
  document.getElementById('editId').value = id;
  document.getElementById('editQ').value  = data.question;
  document.getElementById('editA').value  = data.answer;
  document.getElementById('editK').value  = data.keywords || '';
  document.getElementById('editC').value  = data.category || '';
  document.getElementById('editActive').checked = data.is_active == 1;
  document.getElementById('editModal').style.display = 'flex';
}
document.getElementById('editModal').addEventListener('click', function(e){
  if(e.target===this) this.style.display='none';
});
</script>
</body></html>
