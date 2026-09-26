<?php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

$err = $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        $err = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Enter a valid email address.';
    } else {
        db_insert("INSERT INTO contact_messages(name,email,subject,message) VALUES(?,?,?,?)",
            [$name, $email, $subject, $message]);
        // Notify admin by email
        require_once RF_ROOT.'/mail/mailer.php';
        $adminEmail = setting('site_email', 'info@rideflow.lk');
        rf_mail($adminEmail, 'Admin', "New Contact: $subject",
            mail_template('New Contact Message', "<p><strong style='color:#f0ede6'>From:</strong> $name ($email)</p><p><strong style='color:#f0ede6'>Subject:</strong> $subject</p><p>$message</p>"));
        flash('ok', 'Message sent! We\'ll get back to you within 24 hours.');
        redirect(SITE_URL.'/contact.php');
    }
}

$pageTitle = 'Contact Us';
require_once RF_ROOT.'/includes/header.php';
?>
<div class="main-content">
<div class="container">
  <div style="text-align:center;margin-bottom:2.5rem">
    <h1>Get in Touch</h1>
    <p>We're here to help with any questions about your bookings or travel plans.</p>
  </div>

  <div class="grid-2" style="gap:2.5rem;align-items:start">
    <!-- Contact form -->
    <div>
      <?php if ($err): ?><div class="alert a-bad"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endif; ?>
      <div class="form-card">
        <h2 style="font-size:1.2rem;margin-bottom:1.25rem">Send a Message</h2>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="field-group">
            <div class="fld">
              <label>Your Name</label>
              <div class="fw"><i class="fas fa-user fc-icon"></i>
                <input type="text" name="name" class="fc has-icon" placeholder="John Perera" required value="<?= e($_POST['name']??($_SESSION['uname']??'')) ?>">
              </div>
            </div>
            <div class="fld">
              <label>Email Address</label>
              <div class="fw"><i class="fas fa-envelope fc-icon"></i>
                <input type="email" name="email" class="fc has-icon" placeholder="you@example.com" required value="<?= e($_POST['email']??($_SESSION['uemail']??'')) ?>">
              </div>
            </div>
          </div>
          <div class="fld">
            <label>Subject</label>
            <select name="subject" class="fc" required>
              <option value="">Select a subject…</option>
              <?php foreach (['Booking Issue','Payment Problem','Route Enquiry','Cancellation Request','Technical Support','Feedback','Other'] as $s): ?>
              <option value="<?= $s ?>" <?= ($_POST['subject']??'')===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fld">
            <label>Message</label>
            <textarea name="message" class="fc" rows="5" placeholder="Describe your issue or question in detail…" required><?= e($_POST['message']??'') ?></textarea>
          </div>
          <button type="submit" class="btn btn-fire btn-full btn-lg">
            <i class="fas fa-paper-plane"></i> Send Message
          </button>
        </form>
      </div>
    </div>

    <!-- Contact info -->
    <div>
      <h2 style="font-size:1.2rem;margin-bottom:1.25rem">Other Ways to Reach Us</h2>
      <div style="display:flex;flex-direction:column;gap:.85rem">
        <?php $infos=[
          ['ico'=>'phone',         'col'=>'var(--ok)',  'bg'=>'rgba(34,197,94,.1)',  'title'=>'Phone',     'text'=>'+94 11 234 5678','sub'=>'Mon–Sat, 9am–9pm'],
          ['ico'=>'envelope',      'col'=>'var(--fire)', 'bg'=>'var(--fire-soft)',   'title'=>'Email',     'text'=>'support@rideflow.lk','sub'=>'Reply within 24h'],
          ['ico'=>'map-marker-alt','col'=>'var(--info)', 'bg'=>'rgba(56,189,248,.1)','title'=>'Address',   'text'=>'No.42, Galle Road, Colombo 03','sub'=>'Head Office'],
          ['ico'=>'clock',         'col'=>'var(--warn)', 'bg'=>'rgba(245,158,11,.1)','title'=>'Hours',     'text'=>'Mon–Sat: 9am – 9pm','sub'=>'Sun: 10am – 6pm'],
        ];
        foreach ($infos as $info): ?>
        <div class="card card-pad" style="display:flex;align-items:flex-start;gap:1rem">
          <div style="width:42px;height:42px;border-radius:var(--r3);background:<?= $info['bg'] ?>;color:<?= $info['col'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.95rem"><i class="fas fa-<?= $info['ico'] ?>"></i></div>
          <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--chalk4);margin-bottom:.2rem"><?= $info['title'] ?></div>
            <div style="font-weight:600;color:var(--chalk);font-size:.92rem"><?= e($info['text']) ?></div>
            <div style="font-size:.8rem;color:var(--chalk3)"><?= e($info['sub']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- FAQ shortcut -->
      <div class="card card-pad" style="margin-top:1.25rem;background:var(--fire-soft);border-color:rgba(255,106,0,.2)">
        <h3 style="font-size:.95rem;color:var(--fire);margin-bottom:.5rem"><i class="fas fa-robot"></i> Try Our AI Assistant</h3>
        <p style="font-size:.87rem;margin-bottom:.85rem">Get instant answers to common questions about bookings, payments, and routes from our AI chatbot.</p>
        <button onclick="chatToggle();chatSend('I need help with my booking')" class="btn btn-fire btn-sm"><i class="fas fa-comments"></i> Chat Now</button>
      </div>
    </div>
  </div>
</div>
</div>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
