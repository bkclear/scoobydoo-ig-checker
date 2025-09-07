<?php
/* Scooby Doo — Compact IG Monitor (with User Notes) */
error_reporting(E_ALL); ini_set('display_errors',1); date_default_timezone_set('Asia/Manila');
$cookiesFile=__DIR__.'/cookies.txt'; $dataFile=__DIR__.'/data.json';
if(!file_exists($dataFile)) file_put_contents($dataFile,json_encode(['usernames'=>[],'results'=>[],'lastUpdated'=>null],JSON_PRETTY_PRINT));
if(!file_exists($cookiesFile)) file_put_contents($cookiesFile,'');
function loadData($f){$j=json_decode(@file_get_contents($f),true);return $j?:['usernames'=>[],'results'=>[],'lastUpdated'=>null];}
function saveData($f,$d){file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}
function loadCookies($f){return trim(@file_get_contents($f));}
function saveCookies($f,$c){file_put_contents($f,trim($c));}
function cookieVal($c,$n){return preg_match('/(?:^|;\s*)'.preg_quote($n,'/').'=([^;]+)/',$c,$m)?$m[1]:'';}
function out($a){header('Content-Type:application/json');echo json_encode($a);exit;}
function checkUser($u,$cookies){
  $u=trim($u); if($u==='') return ['username'=>$u,'exists'=>false,'status'=>400,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];
$ch=curl_init('https://i.instagram.com/api/v1/users/web_profile_info/?username='.rawurlencode($u));
  $headers=['Authority: i.instagram.com','Accept:*/*','Origin:https://www.instagram.com','Referer:https://www.instagram.com/','X-IG-App-ID:936619743392459','X-CSRFToken:'.(cookieVal($cookies,'csrftoken')?:'missing'),'Cookie: '.$cookies];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'Mozilla/5.0',CURLOPT_TIMEOUT=>20,CURLOPT_HEADER=>true,CURLOPT_HTTPHEADER=>$headers]);
  $resp=curl_exec($ch); if($resp===false){curl_close($ch);return ['username'=>$u,'exists'=>false,'status'=>0,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];}
  $hsize=curl_getinfo($ch,CURLINFO_HEADER_SIZE); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $body=substr($resp,$hsize);
  if($code===404) return ['username'=>$u,'exists'=>false,'status'=>404,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];
if($code===429) return ['username'=>$u,'exists'=>false,'status'=>429,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];
  if($code>=400)  return ['username'=>$u,'exists'=>false,'status'=>$code,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];
  $j=json_decode($body,true);
if(!$j||!isset($j['data']['user'])){
    if(strpos($body,'login_required')!==false||strpos($body,'not_logged_in')!==false)
      return ['username'=>$u,'exists'=>false,'status'=>403,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];
    return ['username'=>$u,'exists'=>false,'status'=>$code,'followers'=>null,'following'=>null,'full_name'=>'','bio'=>'','profile_pic_url'=>'','fetched_at'=>date('Y-m-d H:i:s')];
  }
  $x=$j['data']['user'];
  $followers=$x['edge_followed_by']['count']??($x['follower_count']??null);
  $following=$x['edge_follow']['count']??($x['following_count']??null);
  $bio=$x['biography']??'';
  $full_name=$x['full_name']??'';
  $profile_pic_url=$x['profile_pic_url']??'';
  return ['username'=>$u,'exists'=>true,'status'=>$code,'followers'=>$followers,'following'=>$following,'full_name'=>$full_name,'bio'=>$bio,'profile_pic_url'=>$profile_pic_url,'fetched_at'=>date('Y-m-d H:i:s')];
}
$data=loadData($dataFile); $cookies=loadCookies($cookiesFile);
$action=$_POST['action']??$_GET['action']??''; $ajax=isset($_POST['ajax'])||isset($_GET['ajax']);
if($ajax){
  switch($action){
    case 'state': out(['ok'=>true,'data'=>$data,'hasCookies'=>$cookies!=='']);
    case 'save_cookies': $c=trim($_POST['cookies']??'');
if(!$c) out(['ok'=>false]); saveCookies($cookiesFile,$c); out(['ok'=>true]);
    case 'add_usernames':
      $raw=trim($_POST['usernames']??''); if(!$raw) out(['ok'=>false,'added'=>[]]);
      $list=preg_split('/[\r\n,]+/',$raw); $clean=[];
      foreach($list as $n){$n=strtolower(trim($n));$n=preg_replace('/[^a-z0-9._]/','',$n);
if($n!=='') $clean[]=$n;}
      $clean=array_values(array_unique($clean));
      $new=array_values(array_diff($clean,$data['usernames']));
      if($new){ $data['usernames']=array_values(array_unique(array_merge($data['usernames'],$new))); saveData($dataFile,$data); }
      out(['ok'=>true,'added'=>$new,'usernames'=>$data['usernames']]);
case 'delete':
      $u=trim($_POST['username']??''); $data['usernames']=array_values(array_filter($data['usernames'],fn($x)=>$x!==$u)); unset($data['results'][$u]); saveData($dataFile,$data); out(['ok'=>true]);
case 'refresh_one':
      if($cookies==='') out(['ok'=>false,'msg'=>'nocookies']); $u=trim($_POST['username']??''); if(!$u) out(['ok'=>false]);
      $r=checkUser($u,$cookies); $data['results'][$u]['followers']=$r['followers']; $data['results'][$u]['following']=$r['following']; $data['results'][$u]['full_name']=$r['full_name']; $data['results'][$u]['bio']=$r['bio']; $data['results'][$u]['profile_pic_url']=$r['profile_pic_url']; $data['results'][$u]['exists']=$r['exists']; $data['results'][$u]['status']=$r['status']; $data['results'][$u]['fetched_at']=$r['fetched_at'];
      $data['lastUpdated']=date('Y-m-d H:i:s'); saveData($dataFile,$data);
      out(['ok'=>true,'result'=>$data['results'][$u],'lastUpdated'=>$data['lastUpdated']]);
case 'refresh_all':
      if($cookies==='') out(['ok'=>false,'msg'=>'nocookies']);
      foreach($data['usernames'] as $u){
        $r=checkUser($u,$cookies);
        $data['results'][$u]['followers']=$r['followers']; $data['results'][$u]['following']=$r['following']; $data['results'][$u]['full_name']=$r['full_name']; $data['results'][$u]['bio']=$r['bio']; $data['results'][$u]['profile_pic_url']=$r['profile_pic_url']; $data['results'][$u]['exists']=$r['exists']; $data['results'][$u]['status']=$r['status']; $data['results'][$u]['fetched_at']=$r['fetched_at'];
      }
      $data['lastUpdated']=date('Y-m-d H:i:s'); saveData($dataFile,$data);
      out(['ok'=>true,'results'=>$data['results'],'lastUpdated'=>$data['lastUpdated']]);
case 'save_notes':
    $u=trim($_POST['username']??''); $n=$_POST['notes']??'';
    if(!$u) out(['ok'=>false]);
    if(!isset($data['results'][$u])) $data['results'][$u]=[];
    $data['results'][$u]['notes']=$n;
    saveData($dataFile,$data);
    out(['ok'=>true]);
    default: out(['ok'=>false,'msg'=>'bad_action']);
}
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Scooby Doo</title>
<style>
/* 📱 Mobile-friendly table */
@media (max-width: 768px) {
  table thead { display: none;
}
  table, table tbody, table tr, table td { display: block; width: 100%;
}
  table tr { margin-bottom: 12px; border: 1px solid #333; border-radius: 8px; padding: 8px; background: #111;
box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
  table td { text-align: left; padding: 6px; white-space: normal;
}
  table td::before { content: attr(data-label); font-weight: bold; color: #0f0; display: block; margin-bottom: 2px;
}
  .grid .metrics-numbers {
    font-size: 1.5em; /* Original size for mobile */
  }
}
/* Desktop specific changes */
@media (min-width: 769px) {
  .grid .metrics-numbers {
    font-size: 1.2em; /* Smaller size for desktop grid view */
  }
}
:root{--neon:#00ff88;--soft:rgba(0,255,136,.25);--card:rgba(0,20,0,.45);--bor:rgba(0,255,136,.45);--txt:#c9ffe6;--mut:#8bd9b5;--bad:#ff3b30;--warn:#ffc107;}
*{box-sizing:border-box} html,body{margin:0;background:#000;color:var(--txt);font-family:ui-monospace,Menlo,monospace}
#mx{position:fixed;inset:0;z-index:-1}
.wrap{max-width:1100px;margin:14px auto;padding:12px;background:rgba(0,0,0,.45);border:1px solid var(--bor);border-radius:14px;backdrop-filter:blur(8px);box-shadow:0 0 0 1px var(--soft),inset 0 0 18px var(--soft)}
h1{margin:0 0 10px;color:var(--neon);text-shadow:0 0 12px var(--neon);font-size:24px;text-align:center}
.tools{display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px;margin:8px 0}
@media(max-width:768px){.tools{grid-template-columns:1fr;}}
.card,.tool{background:var(--card);border:1px solid var(--bor);border-radius:12px;padding:10px;box-shadow:inset 0 0 12px var(--soft)}
input,textarea,button{background:#000;color:var(--neon);border:1px solid var(--neon);border-radius:8px;padding:7px 10px;outline:none}
input:focus,textarea:focus{box-shadow:0 0 0 2px var(--soft);background:#001a0c}
button{cursor:pointer;font-weight:700;transition:.15s} button:hover{background:var(--neon);color:#01331e;box-shadow:0 0 12px var(--neon)}
.row{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
#filter{width:100%}
.view{margin-top:8px;border:1px solid var(--bor);border-radius:12px;padding:10px}
.tabs{display:flex;gap:6px;margin-bottom:6px}
.tabs button{border-radius:999px;padding:6px 10px}
.tabs .on{background:var(--neon);color:#01331e}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px}
.item{position:relative;background:var(--card);border:1px solid var(--bor);border-radius:12px;padding:10px;transition:transform .1s,box-shadow .1s}
.item:hover{transform:translateY(-1px);box-shadow:0 0 10px var(--soft),inset 0 0 12px var(--soft);animation:pulse 1s ease}
@keyframes pulse{0%{box-shadow:0 0 0 0 var(--soft)}70%{box-shadow:0 0 18px 4px var(--soft)}100%{box-shadow:0 0 0 0 var(--soft)}}
.head{display:flex;align-items:center;gap:8px}
.ava{width:26px;height:26px;border-radius:50%;border:1px solid var(--neon);display:flex;align-items:center;justify-content:center;font-size:14px;background:#000;overflow:hidden;}
.ava img{width:100%;height:100%;object-fit:cover;}
.uname{font-weight:800;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bad{margin-left:auto;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:900}
.ok{background:#00ff88;color:#033b23}.no{background:var(--bad);color:#fff}.er{background:var(--warn);color:#2a1f00}.uk{background:#eaff00;color:#222}
.small{margin-top:2px;font-size:5px;opacity:.75}
.act{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px}
.act .timer-input{width:60px;font-size:12px;padding:6px 8px}
.act button{padding:6px;font-size:12px}
.table{overflow-x:auto;border:1px solid var(--bor);border-radius:12px}
table{width:100%;border-collapse:collapse;min-width:720px}
th,td{border-bottom:1px solid rgba(255,255,255,.08);padding:8px 8px;text-align:left;font-size:12px}
thead th{color:var(--mut);background:rgba(0,255,136,.06)}
tfoot td{color:var(--mut)}
.note-field{width:100%;margin-top:8px;font-size:10px;height:50px}
.note{font-size:12px;color:var(--mut);margin-top:4px}
.auto-timer-display { margin-left: auto; color: var(--mut); font-size: 11px; }
.hidden { visibility: hidden; }
#cookie-status{font-size:12px;margin-left:10px;display:flex;align-items:center;gap:4px}
#cookie-status .status-dot{width:8px;height:8px;border-radius:50%;}
.status-ok{background:#00ff88;}.status-error{background:#ff3b30;}
#bulkActions{display:none;align-items:center;gap:8px;margin-top:8px;padding:8px;border:1px solid var(--bor);border-radius:8px}
#bulkActions.show{display:flex}
.bulk-count{color:var(--neon);font-size:12px;font-weight:700}
.bulk-select-all{margin-right:6px}
.toast {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 255, 136, 0.9);
    color: #033b23;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0, 255, 136, 0.4);
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}
.toast.show {
    opacity: 1;
}
</style>
</head><body>
<canvas id="mx"></canvas>
<div class="wrap">
  <h1>Scooby Doo</h1>

  <div class="tools">
    <div class="tool" style="grid-column: span 2;">
      <div class="row" style="justify-content:space-between">
        <div class="row">
          <button id="btnAll">♻ Refresh All</button>
          <button id="btnView">🔀 Toggle View</button>
          <button id="btnExport">⬇ Export JSON</button>
        </div>
        <div class="row" style="font-size:12px;color:var(--mut)">
          Last: <span id="last">Never</span>
          <span id="cookie-status">
            <span class="status-dot"></span>
            <span class="status-text">Checking...</span>
          </span>
        </div>
      </div>
      <div class="row" style="margin-top:6px;justify-content:space-between">
        <input id="filter" type="search" placeholder="Filter usernames..." style="flex:1; margin-right: 6px;" />
        <button id="hideAllBtn">👁️ Hide All</button>
        <button id="showAllBtn" style="display:none;">🚫 Show All</button>
      </div>
    </div>

    <div class="tool">
      <form id="addForm">
        <label>Add usernames</label>
        <textarea id="addBox" rows="3" placeholder="oneuser&#10;two.user"></textarea>
        <div class="row" style="margin-top:6px">
          <button type="submit">➕ Add</button>
          <button id="clearAdd" type="button">🧹</button>
        </div>
      </form>
    </div>

    <div class="tool">
      <form id="cForm">
        <label>Instagram Cookies</label>
        <textarea id="cBox" rows="2" placeholder="sessionid=...;
csrftoken=...; mid=..."></textarea>
        <div class="row" style="margin-top:6px">
          <button type="submit">💾 Save</button>
          <button id="clearC" type="button">🧹</button>
        </div>
      </form>
      <div class="note">Paste full cookie string (single line).</div>
    </div>
  </div>

  <div id="bulkActions">
    <input type="checkbox" id="selectAllCheckbox" class="bulk-select-all" />
    <span class="bulk-count">0 Selected</span>
    <button id="bulkRefreshBtn" class="bulk-action-btn">♻ Refresh</button>
    <button id="bulkDeleteBtn" class="bulk-action-btn">🗑 Delete</button>
    <button id="bulkCopyBtn" class="bulk-action-btn">📋 Copy</button>
  </div>

  <div class="view">
    <div class="tabs"><button id="tGrid" class="on">🧩 Grid</button><button id="tList">📋 List</button></div>

    <div id="grid" class="grid"></div>

    <div id="list" class="table" style="display:none">
      <table>
        <thead>
          <tr><th><input type="checkbox" id="selectAllListCheckbox"/> #</th><th>Username</th><th>Full name</th><th>Followers</th><th>Following</th><th>Status</th><th>Last</th><th>Notes</th><th>Actions</th></tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>
</div>
<div id="toast" class="toast"></div>

<script>
/* Matrix bg */
(()=>{const c=document.getElementById('mx'),x=c.getContext('2d');function rs(){c.width=innerWidth;c.height=innerHeight}addEventListener('resize',rs);rs();const f=14,L="01";let cols=Math.floor(c.width/f),d=Array(cols).fill(1);setInterval(()=>{x.fillStyle="rgba(0,0,0,.05)";x.fillRect(0,0,c.width,c.height);x.fillStyle="#0f0";x.font=f+"px monospace";for(let i=0;i<d.length;i++){const t=L[Math.floor(Math.random()*L.length)];x.fillText(t,i*f,d[i]*f);if(d[i]*f>c.height&&Math.random()>.975)d[i]=0;d[i]++;}},33)})();

const S={view:'grid',usernames:[],results:{},last:'Never',filter:'', selectedUsers: new Set()};
const D={
  q:s=>document.querySelector(s),
  qa:s=>document.querySelectorAll(s),
  el:id=>document.getElementById(id),
};
const timers={};

function showToast(msg) {
  const toast=D.el('toast');
  toast.textContent=msg;
  toast.classList.add('show');
  setTimeout(()=>toast.classList.remove('show'),2000);
}
function formatTime(secs){const m=Math.floor(secs/60);const s=secs%60;return `${m}:${s.toString().padStart(2,'0')}`;}
function badge(r){
  if(!r) return '<span class="bad uk">unknown</span>';
  if(r.exists) return '<span class="bad ok">exists</span>';
  if(r.status===404) return '<span class="bad no">not found</span>';
  return '<span class="bad er">error</span>';
}
function toggleVisibility(btn, username) {
  const containers = D.qa(`[data-username="${username}"] .username-text`);
  containers.forEach(container => {
    const isHidden = container.classList.toggle('hidden');
    btn.textContent = isHidden ? '🚫' : '👁️';
  });
}
function toggleAllVisibility(hide) {
  const users = D.qa('.username-text');
  users.forEach(el => {
    const btn = el.closest('[data-username]').querySelector('.toggle-visibility-btn');
    if (hide) {
      el.classList.add('hidden');
      if (btn) btn.textContent = '🚫';
    } else {
      el.classList.remove('hidden');
      if (btn) btn.textContent = '👁️';
    }
  });
  D.el('hideAllBtn').style.display = hide ? 'none' : 'inline-block';
  D.el('showAllBtn').style.display = hide ? 'inline-block' : 'none';
}
function copyText(t){
  navigator.clipboard.writeText(t).then(()=>showToast('Copied to clipboard'));
}
function setTimerDisplay(user, text){
    D.qa(`[data-username="${user}"] .auto-timer-display`).forEach(el=>el.textContent=text);
}
function toggleSelection(username){
  if(S.selectedUsers.has(username)){
    S.selectedUsers.delete(username);
  } else {
    S.selectedUsers.add(username);
  }
  render();
}
function toggleSelectAll(checked){
  S.selectedUsers.clear();
  if(checked){
    const filteredUsers = S.usernames.filter(u => u.toLowerCase().includes(S.filter.toLowerCase()));
    filteredUsers.forEach(u => S.selectedUsers.add(u));
  }
  render();
}

/* API */
async function api(p){
  const r=await fetch('index.php',{method:'POST',body:new URLSearchParams(Object.assign({ajax:1},p))});
  return r.json();
}
async function loadState(){
    const r=await api({action:'state'});
    if(!r.ok) return;
    S.usernames=r.data.usernames||[];
    S.results=r.data.results||{};
    S.last=r.data.lastUpdated||'Never';
    render();
    checkCookieStatus();
}
async function refreshAll(){
    const b=D.el('btnAll');b.disabled=true;b.textContent='⏳...';
    const r=await api({action:'refresh_all'});
    if(r.ok){
      S.results=r.results||S.results;S.last=r.lastUpdated||S.last;
      render();
      showToast('All users refreshed!');
    } else alert('Set cookies first');
    b.disabled=false;b.textContent='♻ Refresh All';
}
async function refreshOne(u){
    const r=await api({action:'refresh_one',username:u});
    if(r.ok){
        S.results[u]=r.result;S.last=r.lastUpdated||S.last;
        render();
        showToast(`Refreshed @${u}`);
    } else showToast(r.msg==='nocookies'?'Save cookies first':'Failed to refresh');
}
async function deleteUser(u){
    if(!confirm('Delete '+u+' ?')) return;
    const r=await api({action:'delete',username:u});
    if(r.ok){
        S.usernames=S.usernames.filter(x=>x!==u);
        delete S.results[u];
        S.selectedUsers.delete(u);
        render();
        showToast(`Deleted @${u}`);
    } else showToast('Failed to delete user.');
}
async function addUsers(raw){
    const r=await api({action:'add_usernames',usernames:raw});
    if(r.ok){
        S.usernames=r.usernames||S.usernames;
        render();
        if(r.added.length > 0) {
          showToast(`Added ${r.added.length} new users.`);
        } else {
          showToast('No new users to add.');
        }
    } else showToast('Failed to add users.');
}
async function saveCookies(v){
    const r=await api({action:'save_cookies',cookies:v});
    if(r.ok){
      showToast('Cookies saved!');
    } else {
      showToast('Empty cookies, not saved.');
    }
    checkCookieStatus();
}
async function saveNotes(username, notes){
  const r = await api({action: 'save_notes', username: username, notes: notes});
  if(r.ok){
    showToast('Note saved!');
  } else {
    showToast('Failed to save note.');
  }
}
async function checkCookieStatus() {
    const statusDot=D.q('#cookie-status .status-dot');
    const statusText=D.q('#cookie-status .status-text');
    statusText.textContent='Checking...';
    statusDot.className='status-dot status-loading';

    try {
        const response=await api({action:'state'});
        if(response.hasCookies){
            statusDot.className='status-dot status-ok';
            statusText.textContent='Cookies OK';
        } else {
            statusDot.className='status-dot status-error';
            statusText.textContent='No Cookies';
        }
    } catch(error){
        statusDot.className='status-dot status-error';
        statusText.textContent='Check Failed';
    }
}
function toggleAutoRefresh(user, secs){
  if(timers[user]){
    clearInterval(timers[user].interval);
    delete timers[user];
    setTimerDisplay(user,'');
    D.qa(`[data-username="${user}"] .start-auto-btn`).forEach(el=>el.textContent="▶ Auto");
  }else{
    const delay=secs;
    if(isNaN(delay)||delay<=0) return alert('Invalid time');
    D.qa(`[data-username="${user}"] .start-auto-btn`).forEach(el=>el.textContent="⏹ Auto");
    let left=delay;
    setTimerDisplay(user,`⏳ ${formatTime(left)}`);
    timers[user]={interval:null,time:delay,startTime:Date.now()};
    
    const tick=()=>{
      left--;
      if(left<=0){
        refreshOne(user).then(()=>{
          left=delay;
        });
      }
      setTimerDisplay(user,`⏳ ${formatTime(left)}`);
    };
    timers[user].interval=setInterval(tick,1000);
  }
}

/* Bulk Actions */
function updateBulkActionsBar(){
  const count=S.selectedUsers.size;
  const bar=D.q('#bulkActions');
  const countSpan=D.q('.bulk-count');
  const selectAllGrid=D.q('#selectAllCheckbox');
  const selectAllList=D.q('#selectAllListCheckbox');

  bar.classList.toggle('show', count > 0);
  countSpan.textContent=`${count} Selected`;
  const filteredUserCount=S.usernames.filter(u=>u.toLowerCase().includes(S.filter.toLowerCase())).length;
  selectAllGrid.checked=count>0&&count===filteredUserCount;
  selectAllList.checked=count>0&&count===filteredUserCount;
}
function bulkRefresh(){
  if(!S.selectedUsers.size) return;
  S.selectedUsers.forEach(u=>refreshOne(u));
  S.selectedUsers.clear();
  updateBulkActionsBar();
}
function bulkDelete(){
  if(!S.selectedUsers.size) return;
  if(!confirm(`Delete ${S.selectedUsers.size} users?`)) return;
  S.selectedUsers.forEach(u=>deleteUser(u));
  S.selectedUsers.clear();
  updateBulkActionsBar();
}
function bulkCopy(){
  if(!S.selectedUsers.size) return;
  const usernames=Array.from(S.selectedUsers).join("\n");
  navigator.clipboard.writeText(usernames).then(()=>{
    showToast(`Copied ${S.selectedUsers.size} usernames.`);
    S.selectedUsers.clear();
    updateBulkActionsBar();
  });
}

/* Rendering */
function render(){
  D.el('last').textContent = S.last || 'Never';
  if(S.view==='grid'){
    D.q('#grid').style.display='grid'; D.q('#list').style.display='none';
    D.q('#tGrid').classList.add('on'); D.q('#tList').classList.remove('on');
    drawGrid();
}else{
    D.q('#grid').style.display='none'; D.q('#list').style.display='block';
    D.q('#tList').classList.add('on'); D.q('#tGrid').classList.remove('on');
    drawList();
  }
  updateBulkActionsBar();
}
function drawGrid(){
  const g = D.q('#grid');
  g.innerHTML = '';
  let f = S.filter.toLowerCase();
  const filteredUsers = S.usernames.filter(u => u.toLowerCase().includes(f));
  if (filteredUsers.length === 0) {
    g.innerHTML = '<div class="note">No users match filter.</div>';
    return;
  }
  filteredUsers.forEach(u => {
    const r = S.results[u] || {};
    const fl = (r.followers ?? '—');
    const fg = (r.following ?? '—');
    const la = r.fetched_at || '—';
    const isRunning=!!timers[u];
    const notes = r.notes || '';
    const d=document.createElement('div');
    d.className='item';
    d.setAttribute('data-username',u);
    d.innerHTML=`
      <div class="head">
        <input type="checkbox" class="user-checkbox" ${S.selectedUsers.has(u)?'checked':''}/>
        <div class="ava">${r.profile_pic_url?`<img src="${r.profile_pic_url}" alt="Profile Pic">`:'👤'}</div>
        <div class="uname"><span class="username-text" title="@${u}">@${u}</span></div>
        ${badge(r)}
      </div>
      <div>
        <span class="metrics-numbers">Followers: ${fl}</span> |
        <span class="metrics-numbers">Following: ${fg}</span>
        <div class="small">Last: ${la}</div>
      </div>
      <div class="act">
        <button data-a="r">♻</button>
        <input type="number" class="timer-input" placeholder="secs" value="120" min="10"/>
        <button class="start-auto-btn">${isRunning?'⏹ Auto':'▶ Auto'}</button>
        <span class="auto-timer-display"></span>
      </div>
      <div class="row" style="margin-top:6px">
        <button data-a="d">🗑</button>
        <button data-a="c">📋</button>
        <button class="toggle-visibility-btn">👁️</button>
      </div>
      <textarea class="note-field" placeholder="Add notes here..." data-username="${u}">${notes}</textarea>
    `;
    d.querySelector('.user-checkbox').onclick=()=>toggleSelection(u);
    d.querySelector('[data-a="r"]').onclick=()=>refreshOne(u);
    d.querySelector('.start-auto-btn').onclick=()=>{
      const secs=d.querySelector('.timer-input').value;
      toggleAutoRefresh(u,parseInt(secs));
    };
    d.querySelector('[data-a="d"]').onclick=()=>deleteUser(u);
    d.querySelector('[data-a="c"]').onclick=()=>copyText(u);
    d.querySelector('.toggle-visibility-btn').onclick=(e)=>toggleVisibility(e.currentTarget,u);
    d.querySelector('.note-field').onblur=e=>saveNotes(u,e.target.value);
    if(isRunning){
      const remaining=Math.floor((timers[u].time-((Date.now()-timers[u].startTime)/1000)));
      setTimerDisplay(u,`⏳ ${formatTime(remaining)}`);
    }
    g.appendChild(d);
  });
}
function drawList(){
  const tb = D.q('#tbody');
  tb.innerHTML = '';
  let f=S.filter.toLowerCase();
  const filteredUsers = S.usernames.filter(u => u.toLowerCase().includes(f));
  if (filteredUsers.length === 0) {
    tb.innerHTML = '<tr><td colspan="9" class="note">No users match filter.</td></tr>';
    return;
  }
  filteredUsers.forEach((u, idx) => {
    const r=S.results[u]||{};
    const fn=r.full_name||'';
    const fl=(r.followers??'—');
    const fg=(r.following??'—');
    const la=r.fetched_at||'—';
    const isRunning=!!timers[u];
    const notes=r.notes||'';
    const tr=document.createElement('tr');
    tr.setAttribute('data-username',u);
    tr.innerHTML=`
      <td data-label="#"><input type="checkbox" class="user-checkbox" ${S.selectedUsers.has(u)?'checked':''}/> ${idx + 1}</td>
      <td data-label="Username"><span class="ava" style="display:inline-flex;transform:translateY(3px);margin-right:6px">${r.profile_pic_url?`<img src="${r.profile_pic_url}" alt="Profile Pic">`:'👤'}</span><b><span class="username-text">@${u}</span></b></td>
      <td data-label="Full Name">${fn}</td>
      <td data-label="Followers"><span class="metrics-numbers">${fl}</span></td>
      <td data-label="Following"><span class="metrics-numbers">${fg}</span></td>
      <td data-label="Status">${badge(r)}</td>
      <td data-label="Last Check">${la}</td>
      <td data-label="Notes"><textarea class="note-field" placeholder="Add notes here..." data-username="${u}">${notes}</textarea></td>
      <td data-label="Actions">
        <div class="act">
          <button data-a="r">♻</button>
          <input type="number" class="timer-input" placeholder="secs" value="120" min="10"/>
          <button class="start-auto-btn">${isRunning?'⏹ Auto':'▶ Auto'}</button>
          <span class="auto-timer-display"></span>
          <button data-a="d">🗑</button>
          <button data-a="c">📋</button>
          <button class="toggle-visibility-btn">👁️</button>
        </div>
      </td>
    `;
    tr.querySelector('.user-checkbox').onclick=()=>toggleSelection(u);
    tr.querySelector('[data-a="r"]').onclick=()=>refreshOne(u);
    tr.querySelector('.start-auto-btn').onclick=()=>{
      const secs=tr.querySelector('.timer-input').value;
      toggleAutoRefresh(u,parseInt(secs));
    };
    tr.querySelector('[data-a="d"]').onclick=()=>deleteUser(u);
    tr.querySelector('[data-a="c"]').onclick=()=>copyText(u);
    tr.querySelector('.toggle-visibility-btn').onclick=(e)=>toggleVisibility(e.currentTarget,u);
    tr.querySelector('.note-field').onblur=e=>saveNotes(u,e.target.value);
    if(isRunning){
      const remaining=Math.floor((timers[u].time-((Date.now()-timers[u].startTime)/1000)));
      setTimerDisplay(u,`⏳ ${formatTime(remaining)}`);
    }
    tb.appendChild(tr);
  });
}

/* Event Listeners */
document.addEventListener('DOMContentLoaded', ()=>{
  D.el('btnAll').onclick=refreshAll;
  D.el('btnView').onclick=()=>{S.view=S.view==='grid'?'list':'grid';render()};
  D.el('tGrid').onclick=()=>{S.view='grid';render()};
  D.el('tList').onclick=()=>{S.view='list';render()};
  D.el('filter').oninput=e=>{S.filter=e.target.value.trim();render()};
  D.el('addForm').onsubmit=e=>{e.preventDefault();const t=D.el('addBox');if(!t.value.trim())return;addUsers(t.value).then(()=>t.value='')};
  D.el('clearAdd').onclick=()=>D.el('addBox').value='';
  D.el('cForm').onsubmit=e=>{e.preventDefault();const t=D.el('cBox');if(!t.value.trim()){showToast('Cookies field is empty!'); return;} saveCookies(t.value)};
  D.el('clearC').onclick=()=>D.el('cBox').value='';
  D.el('btnExport').onclick=()=>{
    const blob=new Blob([JSON.stringify({usernames:S.usernames,results:S.results,lastUpdated:S.last},null,2)],{type:'application/json'});
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='instagram-monitor-export.json';document.body.appendChild(a);a.click();a.remove();
    setTimeout(()=>URL.revokeObjectURL(url),2000);
    showToast('Exporting data...');
  };
  D.el('hideAllBtn').onclick = () => toggleAllVisibility(true);
  D.el('showAllBtn').onclick = () => toggleAllVisibility(false);
  D.el('selectAllCheckbox').onclick = e => toggleSelectAll(e.target.checked);
  D.el('selectAllListCheckbox').onclick = e => toggleSelectAll(e.target.checked);
  D.el('bulkRefreshBtn').onclick = bulkRefresh;
  D.el('bulkDeleteBtn').onclick = bulkDelete;
  D.el('bulkCopyBtn').onclick = bulkCopy;

  loadState();
  setInterval(checkCookieStatus, 30 * 60 * 1000); // Check every 30 minutes
});
</script>
</body></html>

