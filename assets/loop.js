// EcoLoop Project Studio — plain browser JavaScript, alongside EcoTrade.
const $ = id => document.getElementById(id);
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let state = null, quote = null, currentView = 'studio', pendingAction = null, busy = false;
const templates = {
    desk: {name:'Desktop organiser', description:'A home for pens, notes and little things, built from materials with a second life.', requirements:[{material:'cardboard',quantity:1,unit:'pack',width:30,height:40},{material:'tubes',quantity:3,unit:'piece',width:3,height:10},{material:'fabric and string',quantity:1,unit:'pack',width:20,height:20}]},
    display: {name:'Exhibition display',description:'A reusable display for a school exhibition. A cardboard pack contains three sheets.',requirements:[{material:'cardboard',quantity:1,unit:'pack',width:30,height:40},{material:'paper',quantity:4,unit:'sheet',width:21,height:29}]},
    custom: {name:'',description:'',requirements:[{material:'',quantity:1,unit:'piece',width:0,height:0}]}
};
function notify(message, error = false) {
    $('notice').textContent = message; $('notice').classList.toggle('error', error); $('notice').hidden = false;
    clearTimeout(notify.timer); notify.timer = setTimeout(() => $('notice').hidden = true, 6000);
}
async function api(action, body) {
    const response = await fetch('api/loop.php' + (action ? '?action=' + encodeURIComponent(action) : ''), body ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)} : {});
    if (response.status === 401) { location.href = 'login.php'; throw new Error('Please sign in again.'); }
    const data = await response.json();
    if (!data.success) throw new Error(data.error || 'Unable to complete this action.');
    return data;
}
function art(material) {
    const type = /cardboard/i.test(material) ? 'cardboard' : /tube/i.test(material) ? 'tubes' : /fabric|string/i.test(material) ? 'fabric' : '';
    return type ? `<div class="material-art ${type}" aria-hidden="true"><i></i><i></i>${type==='fabric'?'':'<i></i>'}</div>` : '<div class="generic-art" aria-hidden="true">⌑</div>';
}
function empty(text) { return `<div class="empty-state">${esc(text)}</div>`; }
function button(label, action, id, extra = '') { return `<button type="button" class="button secondary" data-action="${action}" data-id="${esc(id)}" ${extra}>${label}</button>`; }
function showView(view) {
    if (view === 'organiser' && !state?.organiser) return;
    currentView = view;
    document.querySelectorAll('.view').forEach(el => el.hidden = el.id !== 'view-' + view);
    document.querySelectorAll('.topbar [data-view]:not(.section-link)').forEach(el => el.classList.toggle('active', el.dataset.view === view));
    const projectsSection = document.querySelector('.topbar .section-link[data-view]');
    if (projectsSection) projectsSection.classList.toggle('active', ['studio','projects','credits','supply','organiser'].includes(view));
    history.replaceState(null, '', '#' + view); window.scrollTo({top:0, behavior:'smooth'});
}
function addRequirement(value = {}) {
    if ($('requirements').children.length >= 20) return notify('A project can contain up to 20 requirements.', true);
    const div = document.createElement('div'); div.className = 'requirement';
    div.innerHTML = `<div class="requirement-head"><label>Material<input data-key="material" value="${esc(value.material || '')}" placeholder="e.g. cardboard" list="material-types" required maxlength="60"></label><button type="button" class="remove-row" aria-label="Remove material">×</button></div>
        <div class="form-pair"><label>Quantity<input data-key="quantity" type="number" min="1" max="10000" value="${value.quantity || 1}" required></label><label>Unit<select data-key="unit">${['pack','piece','sheet','metre'].map(unit=>`<option ${unit === value.unit ? 'selected' : ''}>${unit}</option>`).join('')}</select></label></div>
        <div class="specs"><label>Min. width<input data-key="width" type="number" min="0" max="10000" value="${value.width || 0}" required></label><label>Min. length<input data-key="height" type="number" min="0" max="10000" value="${value.height || 0}" required></label><label>Condition<select data-key="condition"><option value="usable">Usable</option><option value="like-new" ${value.condition==='like-new'?'selected':''}>Like new</option></select></label></div>
        <label class="checkbox"><input data-key="owned" type="checkbox" ${value.owned?'checked':''}> I already have this</label>`;
    div.querySelector('.remove-row').addEventListener('click', () => { div.remove(); invalidateQuote(); updateCount(); });
    $('requirements').appendChild(div); updateCount();
}
function updateCount() { $('requirement-count').textContent = $('requirements').children.length + ' materials'; }
function requirements() {
    return [...$('requirements').children].map(row => Object.fromEntries([...row.querySelectorAll('[data-key]')].map(el=>[el.dataset.key,el.type==='checkbox'?el.checked:el.type==='number'?Number(el.value):el.value])));
}
function projectInput() { return {...Object.fromEntries(new FormData($('project-form'))), requirements:requirements()}; }
function useTemplate(key) {
    const template = templates[key]; $('project-name').value = template.name; $('project-description').value = template.description;
    $('requirements').replaceChildren(); template.requirements.forEach(addRequirement); invalidateQuote();
    document.querySelectorAll('[data-template]').forEach(el=>el.classList.toggle('active',el.dataset.template===key));
}
function invalidateQuote() {
    quote = null; $('kit-preview').innerHTML = '<div class="kit-empty"><div class="empty-box">⌑</div><h3>A little of this.<br>A little of that.</h3><p>Find materials to see your live kit. Nothing is reserved yet.</p></div>';
}
function renderQuote(data) {
    quote = data; const matched = data.requirements.filter(r=>!r.missing).length;
    const percent = Math.round(matched/data.requirements.length*100); const owners = new Set(data.lines.map(l=>l.owner_id)).size;
    const shortfall = Math.max(0, data.total - state.balance.available);
    $('kit-preview').innerHTML = `<div class="match-state"><span>${percent}% matched</span><span>${owners} ${owners===1?'supplier':'suppliers'}</span></div><div class="progress-track"><i style="width:${percent}%"></i></div>` +
        data.lines.map((line,index)=>`<div class="kit-line" style="animation-delay:${index*90}ms">${art(line.material)}<div><strong>${esc(line.title)}</strong><small>${line.quantity} ${esc(line.unit)} · ${esc(line.owner)}</small></div><b>${line.cost} cr</b></div>`).join('') +
        data.requirements.map(r=>`<div class="match-state ${r.missing?'missing':''}"><span>${esc(r.material)}</span><span>${r.missing?`${r.missing} ${esc(r.unit)} missing`:r.owned?'Already have':'Matched ✓'}</span></div>`).join('') +
        `<div class="kit-total"><span>YOUR PROJECT KIT</span><div><strong>${data.total}</strong> <small>credits</small></div></div><button type="button" id="request-kit" class="button primary full" ${!data.complete || !data.lines.length || shortfall?'disabled':''}>Request kit <span>↗</span></button><p class="kit-note">${!data.complete?'Some pieces are missing. Save a draft and add or find suitable listings.':shortfall?`You need ${shortfall} more credits. Offer useful goods through My credits.`:'Credits and quantities are held for up to 48 hours. Each owner must approve. Payment happens after collection.'}</p>${shortfall?'<button type="button" data-view="credits" class="text-link">Contribute value →</button>':''}`;
}
async function match() {
    if (!$('project-form').reportValidity() || busy) return;
    busy=true; $('match-button').disabled=true; $('match-button').textContent='Finding suitable materials…';
    $('kit-preview').innerHTML='<p class="micro">Checking local inventory, quantities and dimensions…</p><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>';
    try { const data=await api('match',projectInput()); state=data; renderQuote(data); }
    catch (error) { invalidateQuote(); notify(error.message,true); }
    finally { busy=false; $('match-button').disabled=false; $('match-button').textContent='Find my materials ↗'; }
}
function render() {
    $('available-balance').textContent=state.balance.available; $('held-balance').textContent=state.balance.held+' cr';
    $('organiser-nav').hidden=!state.organiser; $('demo-bar').hidden=!state.demo;
    if (state.demo) $('demo-role').value=state.user.id;
    $('inventory-grid').innerHTML=state.inventory.filter(i=>i.available>0).slice(0,8).map(i=>`<article class="inventory-card"><div class="inventory-art">${i.image && /^(uploads|assets)\//.test(i.image)?`<img src="${esc(i.image)}" alt="${esc(i.title)}" loading="lazy">`:art(i.material)}<span class="tag">PROJECT READY</span></div><div class="inventory-meta"><h3>${esc(i.title)}</h3><p>${i.available} ${esc(i.unit)} available · ${i.width} × ${i.height} cm</p><div><strong>${i.credits?i.credits+' cr / '+esc(i.unit):'Gift'}</strong><small>${esc(i.owner_username)}</small></div></div></article>`).join('') || empty('No project materials yet. Marketplace owners can opt their listings in through Offer materials.');
    $('project-list').innerHTML=state.projects.slice().reverse().map(projectCard).join('') || empty('Every project starts somewhere. Open the Studio to describe your first idea.');
    $('shelf-grid').innerHTML=state.stock.filter(s=>s.status!=='redeemed').map(s=>`<article class="inventory-card"><div class="inventory-art"><div class="generic-art" aria-hidden="true">⌑</div><span class="tag">COMMUNITY SHELF</span></div><div class="inventory-meta"><h3>${esc(s.title)}</h3><p>${esc(s.description)}</p><div><strong>${s.value} cr</strong>${s.status==='available'?button('Request ↗','redeem-request',s.id):s.requested_by===state.user.id?button('Cancel request','release-stock',s.id):'<small>Awaiting collection</small>'}</div></div></article>`).join('') || empty('The shelf is waiting for useful contributions. Accepted goods will appear here.');
    $('contribution-list').innerHTML=state.contributions.filter(c=>c.owner_id===state.user.id).map(c=>`<div class="list-row"><span>${esc(c.title)}<small>${esc(c.status)}</small></span><strong>${c.value} cr</strong></div>`).join('') || '<p class="micro">No contributions yet. An offer stays pending until physical acceptance.</p>';
    $('ledger-list').innerHTML=state.ledger.slice().reverse().map(e=>`<div class="list-row"><span>${esc(e.label)}<small>${new Date(e.created_at*1000).toLocaleDateString()} · ${esc(e.type)}</small></span><strong class="amount">${e.amount>0?'+':''}${e.amount} cr</strong></div>`).join('') || '<p class="micro">Your confirmed exchanges will appear here.</p>';
    const previous=$('material-item').value;
    $('material-item').innerHTML=state.my_items.length?state.my_items.map(i=>`<option value="${esc(i.id)}">${esc(i.title)}</option>`).join(''):'<option value="">Create a marketplace listing first</option>';
    if (state.my_items.some(i=>i.id===previous)) $('material-item').value=previous;
    if (state.organiser) renderOrganiser();
}
function projectCard(p) {
    const mine=p.maker_id===state.user.id; const supplied=p.lines.filter(l=>l.owner_id===state.user.id); const active=['pending','reserved'].includes(p.status);
    const ready=p.status==='reserved' && p.lines.every(l=>l.checked);
    return `<article class="project-card"><div class="project-head"><div><span class="eyebrow">${esc(p.maker)} / ${p.total} CREDITS</span><h2>${esc(p.name)}</h2><p class="micro">${esc(p.description)}</p></div><span class="pill">${esc(p.status)}</span></div><div class="supplier-network">${p.lines.map(l=>`<div class="supplier-node"><strong>${esc(l.owner)} <span class="amount">+${l.cost} cr</span></strong><p>${l.quantity} ${esc(l.unit)} · ${esc(l.title)}</p><small>${['collected','finished'].includes(p.status)?'Paid after collection ✓':!active?'Request closed':l.checked?'Checked in ✓':l.approved?'Owner approved ✓':'Awaiting owner approval'}</small>${state.organiser && p.status==='reserved' && !l.checked?button('Check material in','checkin',p.id,`data-line="${l.id}"`):''}</div>`).join('')}</div>${p.requirements.filter(r=>r.missing).map(r=>`<p class="project-note">Missing: ${r.missing} ${esc(r.unit)} of ${esc(r.material)}</p>`).join('')}<p class="project-note">${active?'Reservation expires '+new Date(p.expires*1000).toLocaleString()+'. Credits are held, not paid.':'Collection date: '+esc(p.deadline)}</p>${p.outcome?`<p class="project-note">What we made: ${esc(p.outcome)}</p>`:''}${p.return_required?.length?'<p class="project-note">Materials were already received. The organiser must return them to their suppliers.</p>':''}<div class="project-actions">${p.status==='pending' && supplied.some(l=>!l.approved)?button('Approve my materials','approve',p.id):''}${mine && ready?button('Confirm complete-kit collection','collect',p.id):''}${(mine||supplied.length) && active?button('Cancel kit','cancel',p.id):''}${mine && p.status==='collected'?button('Record finished project','finish',p.id):''}${mine && ['draft','cancelled','expired','finished'].includes(p.status)?button(p.status==='finished'?'Reuse this plan':'Edit in Studio','reuse',p.id):''}${p.status==='finished' && mine?'<a href="dashboard.php" class="button secondary">List leftover materials ↗</a>':''}${state.organiser && p.return_required?.length?button('Confirm materials returned','returned',p.id):''}</div></article>`;
}
function renderOrganiser() {
    const r=state.reconciliation;
    $('reconciliation').innerHTML=`<div><small>Credits in circulation</small><strong>${r.circulation}</strong></div><div><small>Shared-stock value</small><strong>${r.coverage}</strong></div><div><small>Stock coverage</small><strong>${r.circulation===r.coverage?'Balanced':'Review needed'}</strong></div>`;
    $('intake-list').innerHTML=state.contributions.filter(c=>c.status==='pending').map(c=>`<div class="list-row"><span><strong>${esc(c.title)}</strong><small>${esc(c.owner)} · ${esc(c.description)} · ${c.value} cr agreed on acceptance</small></span><div>${button('Accept goods','intake',c.id)} ${button('Decline','decline-intake',c.id)}</div></div>`).join('') || empty('No contributions waiting for review.');
    $('collection-list').innerHTML=state.projects.filter(p=>['pending','reserved'].includes(p.status) || p.return_required?.length).map(projectCard).join('') || empty('No kits waiting at the collection desk.');
    $('redemption-list').innerHTML=state.stock.filter(s=>s.status==='requested').map(s=>`<div class="list-row"><span>${esc(s.title)}<small>${esc(s.requested_name)} · ${s.value} cr</small></span><div>${button('Confirm handover','redeem',s.id)} ${button('Release request','release-stock',s.id)}</div></div>`).join('') || empty('No shelf handovers waiting.');
}
function fillMaterial() {
    const item=state?.my_items.find(i=>i.id===$('material-item').value); const m=item?.material;
    if (!m) return;
    Object.entries(m).forEach(([name,value])=>{const el=$('material-form').elements.namedItem(name); if(el) el.type==='checkbox'?el.checked=value:el.value=value;});
}
const confirmations = {
    intake:['Accept a useful contribution','Check the actual goods, condition and usefulness. Accept only if you and the owner agree to the proposed credit value.','I have received these goods into shared stock and confirmed the agreed value.'],
    checkin:['Check this material in','Verify the quantity, dimensions and condition against the project requirements. These goods still belong to their supplier.','I physically received and inspected this material.'],
    collect:['Bring your project to life','This confirms the complete handover and pays each supplier their agreed credits.','I received and accept every material in this kit.'],
    redeem:['Complete the shelf handover','The member receives this item and its credit value is retired from circulation.','I handed the goods to the requesting member.'],
    returned:['Return checked-in materials','The kit was cancelled or expired. Return the received goods to their original suppliers.','All checked-in materials have been returned.'],
    cancel:['Cancel this kit?','Held credits and reserved quantities will be released. Any checked-in goods must be returned to their suppliers.','I want to cancel this kit.'],
    approve:['Your materials. Your approval.','You agree to the displayed quantity and credit value. You will be paid only after the maker collects the complete kit.','I agree to supply my materials on these terms.']
};
function openAction(action,id,line) {
    if (action==='reuse') {
        const p=state.projects.find(p=>p.id===id); $('project-name').value=p.name; $('project-description').value=p.description;
        $('requirements').replaceChildren(); p.raw_requirements.forEach(addRequirement); invalidateQuote(); showView('studio'); $('workspace').scrollIntoView(); return;
    }
    pendingAction={action,id,...(line?{line_id:line}:{})};
    const config=confirmations[action] || (action==='finish'?['What did you make?','Record what worked, how you used the materials, and what can return to the marketplace.']:['Confirm this request','Your request will be saved for the community organiser.']);
    $('dialog-title').textContent=config[0]; $('dialog-description').textContent=config[1];
    $('dialog-fields').innerHTML=action==='finish'?'<label>Project outcome<textarea name="outcome" rows="5" required maxlength="2000" placeholder="What I made, materials used and usable leftovers…"></textarea></label>':config[2]?`<label class="checkbox"><input name="confirmed" type="checkbox" required> ${esc(config[2])}</label>`:'';
    $('action-dialog').showModal();
}
document.addEventListener('click',async event=>{
    const view=event.target.closest('[data-view]'); if(view) showView(view.dataset.view);
    const template=event.target.closest('[data-template]'); if(template) useTemplate(template.dataset.template);
    const action=event.target.closest('[data-action]'); if(action) openAction(action.dataset.action,action.dataset.id,action.dataset.line);
    if (event.target.closest('#request-kit') && quote && !busy) {
        busy=true; event.target.closest('#request-kit').disabled=true;
        try {state=await api('request',{...projectInput(),expected_total:quote.total,expected_quote:quote.quote_key});render();invalidateQuote();showView('projects');notify('Kit requested. Each supplier can now approve their materials.');}
        catch(error){invalidateQuote();notify(error.message,true);} finally{busy=false;}
    }
});
$('action-form').addEventListener('submit',async event=>{
    event.preventDefault(); if(busy || !pendingAction)return; busy=true; $('dialog-submit').disabled=true;
    try {const {action,...payload}=pendingAction;const fields=Object.fromEntries(new FormData(event.currentTarget));state=await api(action,{...payload,...fields});render();$('action-dialog').close();notify('Saved. The community loop is up to date.');}
    catch(error){notify(error.message,true);}finally{busy=false;$('dialog-submit').disabled=false;}
});
$('project-form').addEventListener('submit',event=>{event.preventDefault();match();});
$('requirements').addEventListener('input',invalidateQuote);
$('add-requirement').addEventListener('click',()=>{addRequirement();invalidateQuote();});
$('save-draft').addEventListener('click',async()=>{
    if(!$('project-form').reportValidity()||busy)return;busy=true;
    try{state=await api('draft',projectInput());render();showView('projects');notify('Draft saved. You can reopen and match it later.');}catch(error){notify(error.message,true);}finally{busy=false;}
});
for(const [form,action] of [['contribution-form','contribute'],['material-form','material']]) $(form).addEventListener('submit',async event=>{
    event.preventDefault();if(busy)return;busy=true;const btn=event.currentTarget.querySelector('button[type="submit"],button:not([type])');btn.disabled=true;
    try{const data=Object.fromEntries(new FormData(event.currentTarget));if(form==='material-form')data.enabled=event.currentTarget.elements.enabled.checked;state=await api(action,data);render();if(form==='contribution-form')$(form).reset();notify(action==='material'?'Project terms saved. You control every request.':'Contribution offered. Credits follow physical acceptance.');}catch(error){notify(error.message,true);}finally{busy=false;btn.disabled=false;}
});
$('material-item').addEventListener('change',fillMaterial);
$('demo-role').addEventListener('change',async event=>{
    try{const response=await fetch('api/demo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:event.target.value})});const data=await response.json();if(!data.success)throw new Error(data.error);location.reload();}catch(error){notify(error.message,true);}
});
$('refresh-data').addEventListener('click',async()=>{try{state=await api();render();notify('Updated from the community.');}catch(error){notify(error.message,true);}});
const deadline=new Date();deadline.setDate(deadline.getDate()+7);$('project-deadline').value=deadline.toLocaleDateString('en-CA');$('project-deadline').min=new Date().toLocaleDateString('en-CA');
useTemplate('desk');
(async()=>{try{state=await api();render();fillMaterial();const view=location.hash.slice(1);if(['studio','projects','credits','supply','organiser'].includes(view))showView(view);}catch(error){notify(error.message,true);$('inventory-grid').innerHTML=empty('Unable to load the community. Use Refresh to try again.');}})();

