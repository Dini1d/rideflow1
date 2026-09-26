<?php
/**
 * RideFlow — Public Footer Template
 */
?>
<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">Ride<em>Flow</em></div>
        <p class="footer-desc">Sri Lanka's trusted transport booking platform. Fast, secure, and always on time.</p>
        <div style="display:flex;gap:.6rem;margin-top:1rem">
          <a href="#" style="width:34px;height:34px;border-radius:50%;background:var(--veil2);border:1px solid var(--rim);display:flex;align-items:center;justify-content:center;color:var(--chalk3);font-size:.85rem;transition:all .15s" onmouseover="this.style.background='rgba(255,106,0,.15)';this.style.color='var(--fire)'" onmouseout="this.style.background='var(--veil2)';this.style.color='var(--chalk3)'"><i class="fab fa-facebook-f"></i></a>
          <a href="#" style="width:34px;height:34px;border-radius:50%;background:var(--veil2);border:1px solid var(--rim);display:flex;align-items:center;justify-content:center;color:var(--chalk3);font-size:.85rem;transition:all .15s" onmouseover="this.style.background='rgba(255,106,0,.15)';this.style.color='var(--fire)'" onmouseout="this.style.background='var(--veil2)';this.style.color='var(--chalk3)'"><i class="fab fa-instagram"></i></a>
          <a href="#" style="width:34px;height:34px;border-radius:50%;background:var(--veil2);border:1px solid var(--rim);display:flex;align-items:center;justify-content:center;color:var(--chalk3);font-size:.85rem;transition:all .15s" onmouseover="this.style.background='rgba(255,106,0,.15)';this.style.color='var(--fire)'" onmouseout="this.style.background='var(--veil2)';this.style.color='var(--chalk3)'"><i class="fab fa-twitter"></i></a>
        </div>
      </div>
      <div>
        <div class="footer-heading">Quick Links</div>
        <ul class="footer-links">
          <li><a href="<?= SITE_URL ?>">Home</a></li>
          <li><a href="<?= SITE_URL ?>/search.php">Search Trips</a></li>
          <li><a href="<?= SITE_URL ?>/contact.php">Contact</a></li>
          <li><a href="<?= SITE_URL ?>/login.php">Sign In</a></li>
          <li><a href="<?= SITE_URL ?>/register.php">Register</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-heading">Transport</div>
        <ul class="footer-links">
          <li><a href="<?= SITE_URL ?>/search.php?type=Bus">Bus Routes</a></li>
          <li><a href="<?= SITE_URL ?>/search.php?type=Train">Train Services</a></li>
          <li><a href="<?= SITE_URL ?>/search.php?type=Three-Wheel">Three-Wheel</a></li>
          <li><a href="<?= SITE_URL ?>/search.php?type=Taxi">Taxi</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-heading">Contact</div>
        <ul class="footer-links">
          <li><a href="tel:+94112345678">+94 11 234 5678</a></li>
          <li><a href="mailto:info@rideflow.lk">info@rideflow.lk</a></li>
          <li style="color:var(--chalk4);font-size:.85rem">No.42, Galle Road,<br>Colombo 03</li>
        </ul>
        <!-- Newsletter -->
        <div style="margin-top:1rem">
          <div class="footer-heading">Newsletter</div>
          <form id="newsletterForm" style="display:flex;gap:.4rem">
            <input type="email" placeholder="your@email.com" id="nlEmail" class="fc" style="padding:.5rem .8rem;font-size:.82rem;flex:1;min-width:0">
            <button type="submit" class="btn btn-fire btn-sm" style="flex-shrink:0"><i class="fas fa-paper-plane"></i></button>
          </form>
          <div id="nlMsg" style="font-size:.75rem;margin-top:.3rem;display:none"></div>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> RideFlow. All rights reserved.</span>
      <div style="display:flex;gap:1.25rem">
        <a href="#" style="color:var(--chalk4)">Privacy</a>
        <a href="#" style="color:var(--chalk4)">Terms</a>
        <a href="#" style="color:var(--chalk4)">Support</a>
      </div>
    </div>
  </div>
</footer>

<!-- ══════════════════════════════
     AI CHATBOT WIDGET
══════════════════════════════ -->
<button class="chat-fab" id="chatFab" onclick="chatToggle()" title="Chat with RideFlow AI">
  <i class="fas fa-robot" id="chatFabIco"></i>
  <span class="chat-badge"></span>
</button>

<div class="chat-win" id="chatWin">
  <div class="chat-head">
    <div class="chat-av"><i class="fas fa-robot"></i></div>
    <div>
      <div class="chat-name">RideFlow Assistant</div>
      <div style="font-size:.7rem;color:rgba(255,255,255,.5)"><span class="chat-status-dot"></span>Online · AI Powered</div>
    </div>
    <button class="chat-close" onclick="chatToggle()"><i class="fas fa-times"></i></button>
  </div>
  <div class="chat-msgs" id="chatMsgs"></div>
  <div class="chat-qr" id="chatQR">
    <button class="chat-qr-btn" onclick="chatSend('Show available bus routes')">🚌 Bus Routes</button>
    <button class="chat-qr-btn" onclick="chatSend('How do I book a ticket?')">🎟 How to Book</button>
    <button class="chat-qr-btn" onclick="chatSend('What payment methods are accepted?')">💳 Payments</button>
    <button class="chat-qr-btn" onclick="chatSend('How to cancel my booking?')">❌ Cancel</button>
  </div>
  <div class="chat-input-row">
    <textarea class="chat-input" id="chatInput" placeholder="Ask about routes, bookings…" rows="1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();chatSendInput()}"
      oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,90)+'px'"></textarea>
    <button class="chat-send" id="chatSendBtn" onclick="chatSendInput()"><i class="fas fa-paper-plane"></i></button>
  </div>
  <div class="chat-powered">Powered by Claude AI · <a href="<?= SITE_URL ?>/contact.php" style="color:var(--chalk4)">Contact Support</a></div>
</div>

<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
<script>
/* Newsletter */
document.getElementById('newsletterForm').addEventListener('submit',function(e){
  e.preventDefault();
  var em=document.getElementById('nlEmail').value.trim();
  var msg=document.getElementById('nlMsg');
  if(!em)return;
  fetch(SITE_URL+'/api/newsletter.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:em})})
    .then(r=>r.json()).then(d=>{
      msg.style.display='block';
      msg.style.color=d.ok?'var(--ok)':'var(--bad)';
      msg.textContent=d.ok?'✓ Subscribed! Thank you.':d.message||'Error.';
      if(d.ok) document.getElementById('nlEmail').value='';
    }).catch(()=>{msg.style.display='block';msg.style.color='var(--bad)';msg.textContent='Network error.';});
});
/* Mobile nav */
function toggleMobileMenu(){
  var s=document.querySelector('.admin-sidebar')||document.querySelector('.nav-links');
  if(s)s.style.display=s.style.display==='flex'?'none':'flex';
}
</script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
