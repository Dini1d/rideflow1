/**
 * RideFlow — Main Application JavaScript
 */
'use strict';

/* ── CHATBOT ─────────────────────────────────────────────── */
var chatOpen  = false;
var chatTyping= false;
var chatHistory=[];

function chatToggle(){
  chatOpen=!chatOpen;
  var win=document.getElementById('chatWin');
  var ico=document.getElementById('chatFabIco');
  var fab=document.getElementById('chatFab');
  if(!win)return;
  if(chatOpen){
    win.classList.add('open');
    if(ico) ico.className='fas fa-times';
    fab.classList.remove('has-unread');
    if(document.getElementById('chatMsgs').children.length===0) chatInit();
    setTimeout(function(){ var inp=document.getElementById('chatInput'); if(inp) inp.focus(); },300);
  } else {
    win.classList.remove('open');
    if(ico) ico.className='fas fa-robot';
  }
}

function chatInit(){
  appendBotMsg("👋 Hi! I'm RideFlow Assistant. I can help you find routes, book tickets, and answer any questions about travelling in Sri Lanka. How can I help you today?");
}

function appendBotMsg(text){
  var msgs=document.getElementById('chatMsgs');
  if(!msgs)return;
  var d=document.createElement('div');
  d.className='chat-msg bot';
  d.innerHTML='<div style="width:22px;height:22px;border-radius:50%;background:var(--fire);display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0"><i class="fas fa-robot" style="color:#fff"></i></div><div class="msg-bub">'+escHtml(text).replace(/\n/g,'<br>')+'</div>';
  msgs.appendChild(d);
  msgs.scrollTop=msgs.scrollHeight;
}

function appendUserMsg(text){
  var msgs=document.getElementById('chatMsgs');
  if(!msgs)return;
  var d=document.createElement('div');
  d.className='chat-msg usr';
  d.innerHTML='<div class="msg-bub">'+escHtml(text)+'</div>';
  msgs.appendChild(d);
  msgs.scrollTop=msgs.scrollHeight;
}

function showTyping(){
  var msgs=document.getElementById('chatMsgs');
  if(!msgs)return;
  var d=document.createElement('div');
  d.className='chat-msg bot'; d.id='chatTypingEl';
  d.innerHTML='<div style="width:22px;height:22px;border-radius:50%;background:var(--fire);display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0"><i class="fas fa-robot" style="color:#fff"></i></div><div class="msg-bub" style="padding:.4rem .6rem"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
  msgs.appendChild(d);
  msgs.scrollTop=msgs.scrollHeight;
}
function hideTyping(){ var el=document.getElementById('chatTypingEl'); if(el) el.remove(); }

function chatSendInput(){
  var inp=document.getElementById('chatInput');
  if(!inp) return;
  var txt=inp.value.trim();
  if(!txt||chatTyping)return;
  inp.value=''; inp.style.height='auto';
  chatSend(txt);
}

function chatSend(text){
  if(!text||chatTyping)return;
  var qr=document.getElementById('chatQR');
  if(qr) qr.style.display='none';
  appendUserMsg(text);
  chatHistory.push({role:'user',content:text});
  chatTyping=true;
  var btn=document.getElementById('chatSendBtn');
  if(btn) btn.disabled=true;
  showTyping();
  fetch(SITE_URL+'/chatbot/chat.php',{
    method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({message:text,history:chatHistory.slice(-8)})
  })
  .then(function(r){return r.json();})
  .then(function(d){
    hideTyping(); chatTyping=false; if(btn) btn.disabled=false;
    var reply=d.reply||(d.error?'Sorry, I had trouble responding: '+d.error:'Sorry, something went wrong.');
    appendBotMsg(reply);
    chatHistory.push({role:'assistant',content:reply});
    if(chatHistory.length>20) chatHistory=chatHistory.slice(-20);
    if(!chatOpen){var fab=document.getElementById('chatFab');if(fab)fab.classList.add('has-unread');}
  })
  .catch(function(){
    hideTyping(); chatTyping=false; if(btn) btn.disabled=false;
    appendBotMsg('Sorry, I\'m having connection issues. Please try again.');
  });
}

/* ── SMART USER MENU ───────────────────────────────────── */
(function(){
  var menuWrap=document.querySelector('[data-user-menu]');
  if(!menuWrap) return;
  var toggle=menuWrap.querySelector('.nav-user-toggle');
  var dropdown=menuWrap.querySelector('.nav-dropdown');

  function closeMenu(){
    menuWrap.classList.remove('open');
    if(toggle) toggle.setAttribute('aria-expanded','false');
  }

  function openMenu(){
    menuWrap.classList.add('open');
    if(toggle) toggle.setAttribute('aria-expanded','true');
  }

  if(toggle){
    toggle.addEventListener('click',function(e){
      e.stopPropagation();
      var isOpen=menuWrap.classList.contains('open');
      if(isOpen) closeMenu(); else openMenu();
    });
  }

  if(dropdown){
    dropdown.addEventListener('click',function(e){ e.stopPropagation(); });
  }

  document.addEventListener('click',function(e){
    if(!menuWrap.contains(e.target)) closeMenu();
  });

  document.addEventListener('keydown',function(e){
    if(e.key==='Escape') closeMenu();
  });
})();

/* ── NOTIFICATIONS (live poll) ───────────────────────────── */
(function(){
  if(!document.querySelector('.navbar')) return;
  function pollNotifs(){
    fetch(SITE_URL+'/api/notifications.php',{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(d){
        if(!d.ok) return;
        var cnt=d.data.count||0;
        var badge=document.querySelector('[data-notif-badge]');
        if(badge) badge.textContent=cnt>0?cnt:'';
      }).catch(function(){});
  }
  setInterval(pollNotifs,30000);
})();

/* ── SEARCH PAGE ─────────────────────────────────────────── */
function initSearchPage(){
  var form=document.getElementById('searchForm');
  if(!form) return;
  // Swap origin/destination
  var swap=document.getElementById('swapBtn');
  if(swap) swap.addEventListener('click',function(){
    var from=document.getElementById('search_from');
    var to  =document.getElementById('search_to');
    if(from&&to){var tmp=from.value;from.value=to.value;to.value=tmp;}
  });
  // Set today as default date
  var dateInp=document.getElementById('search_date');
  if(dateInp&&!dateInp.value) dateInp.valueAsDate=new Date();
}

/* ── SEAT SELECTOR ───────────────────────────────────────── */
function initSeatSelector(){
  var seatsInput=document.getElementById('seats_booked');
  if(!seatsInput) return;
  document.querySelectorAll('.seat-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      document.querySelectorAll('.seat-btn').forEach(function(b){
        b.classList.remove('selected');
        b.style.background='';b.style.borderColor='';b.style.color='';
      });
      btn.classList.add('selected');
      btn.style.background='var(--fire)';btn.style.borderColor='var(--fire)';btn.style.color='#fff';
      seatsInput.value=btn.dataset.val;
      var total=document.getElementById('totalFare');
      if(total){
        var fare=parseFloat(total.dataset.fare||0);
        total.textContent='Rs. '+numberFmt(fare*parseInt(btn.dataset.val));
      }
    });
  });
}

/* ── PAYMENT GATEWAY SELECTOR ────────────────────────────── */
function selectGateway(gw){
  document.querySelectorAll('.gw-option').forEach(function(el){
    el.classList.remove('selected');
    el.style.borderColor='';
  });
  var sel=document.querySelector('[data-gw="'+gw+'"]');
  if(sel){sel.classList.add('selected');sel.style.borderColor='var(--fire)';}
  var inp=document.getElementById('payment_gateway');
  if(inp) inp.value=gw;
  // Show/hide gateway-specific fields
  document.querySelectorAll('.gw-fields').forEach(function(el){el.style.display='none';});
  var fields=document.getElementById('gw-'+gw);
  if(fields) fields.style.display='block';
}

/* ── UTILITIES ───────────────────────────────────────────── */
function escHtml(t){
  var d=document.createElement('div');
  d.appendChild(document.createTextNode(t));
  return d.innerHTML;
}
function numberFmt(n){ return Number(n).toLocaleString('en-LK',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function tglPw(id,ico){
  var el=document.getElementById(id),ic=document.getElementById(ico);
  if(!el)return;
  el.type=el.type==='password'?'text':'password';
  if(ic) ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}

/* ── TOAST ───────────────────────────────────────────────── */
function toast(msg,type){
  type=type||'ok';
  var t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:5.5rem;right:1.5rem;background:var(--ink2);border:1px solid var(--rim2);border-radius:var(--r3);padding:.75rem 1.1rem;font-size:.85rem;color:var(--chalk);box-shadow:0 8px 24px rgba(0,0,0,.5);z-index:1100;display:flex;align-items:center;gap:.5rem;animation:fadeIn .2s ease;max-width:320px';
  var colors={'ok':'var(--ok)','bad':'var(--bad)','warn':'var(--warn)','info':'var(--info)'};
  var icons={'ok':'check-circle','bad':'times-circle','warn':'exclamation-triangle','info':'info-circle'};
  t.innerHTML='<i class="fas fa-'+(icons[type]||'info-circle')+'" style="color:'+(colors[type]||'var(--ok)')+'"></i>'+escHtml(msg);
  document.body.appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transform='translateY(10px)';t.style.transition='all .3s';setTimeout(function(){t.remove();},350);},3500);
}

/* ── CONFIRM DIALOG ──────────────────────────────────────── */
function confirmAction(msg,callback){
  if(window.confirm(msg)) callback();
}

/* ── AUTO-INIT ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded',function(){
  initSearchPage();
  initSeatSelector();
  // Auto-dismiss alerts
  setTimeout(function(){
    document.querySelectorAll('.alert').forEach(function(a){
      a.style.transition='opacity .5s';a.style.opacity='0';
      setTimeout(function(){a.remove();},500);
    });
  },5000);
});

/* ── PRINT TICKET ────────────────────────────────────────── */
function printTicket(){ window.print(); }
