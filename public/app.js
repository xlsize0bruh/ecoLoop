import { icon, illustration } from './art.js';

// EcoTrade's escaped HTML rendering, request lifecycle and participant confirmations
// are carried forward into the project-kit and credit workflows.
const escapeHtml = str => String(str ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
const e = escapeHtml;
const $ = (q, root = document) => root.querySelector(q);
const $$ = (q, root = document) => [...root.querySelectorAll(q)];
const state = { data: null, route: 'market', filter: { q: '', category: 'All items', mode: 'all' }, match: null, modal: null, chat: null, confirmation: null };
const pretty = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
const formatDate = ms => new Intl.DateTimeFormat('en-IN', { month: 'short', day: 'numeric' }).format(new Date(ms));
const fullDate = ms => new Intl.DateTimeFormat('en-IN', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(ms));
const initials = name => String(name || 'Community').split(' ').slice(0, 2).map(x => x[0]).join('').toUpperCase();
const badge = value => `<span class="badge ${e(value)}">${e(pretty(value))}</span>`;
const button = (label, action, value = '', style = 'primary', symbol = '') => `<button class="btn ${style}" data-action="${action}" data-id="${e(value)}">${symbol ? icon(symbol) : ''}${label}</button>`;
const options = (data, value = '') => (Array.isArray(data) ? data.map(x => [x,x]) : Object.entries(data)).map(([key,label]) => `<option value="${e(key)}"${key === value ? ' selected' : ''}>${e(label)}</option>`).join('');
const field = (label, html, full = false, help = '') => `<label class="field${full ? ' full' : ''}"><span class="field-label">${label}</span>${html}${help ? `<small>${help}</small>` : ''}</label>`;
const input = (name, value = '', type = 'text', extra = '') => `<input name="${name}" type="${type}" value="${e(value)}" ${extra}>`;
const area = (name, value = '', extra = '') => `<textarea name="${name}" ${extra}>${e(value)}</textarea>`;
const select = (name, data, value) => `<select name="${name}">${options(data, value)}</select>`;
const empty = (title, description, cta = '', symbol = 'leaf') => `<div class="empty">${icon(symbol)}<h3>${title}</h3><p>${description}</p>${cta}</div>`;

async function api(path, data) {
  const response = await fetch('/api/' + path, data === undefined ? { credentials: 'same-origin' } : {
    method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json', 'X-CSRF-Token': state.data?.csrf || '' }, body: JSON.stringify(data),
  });
  const body = await response.json();
  if (!response.ok) throw new Error(body.error || 'Something went wrong. Please try again.');
  return body;
}
function toast(message, error = false) {
  $('#toast-region').innerHTML = `<div class="toast${error ? ' error' : ''}">${icon(error ? 'info' : 'check')}<span>${e(message)}</span></div>`;
  clearTimeout(toast.timer); toast.timer = setTimeout(() => $('#toast-region').replaceChildren(), 5000);
}
function openModal(title, subtitle, content, wide = false) {
  state.chat = null;
  state.modal = document.activeElement;
  $('#overlay').innerHTML = `<div class="backdrop"><section class="modal${wide ? ' wide' : ''}" role="dialog" aria-modal="true" aria-labelledby="modal-title"><header class="modal-head"><div><h2 id="modal-title">${title}</h2>${subtitle ? `<p>${subtitle}</p>` : ''}</div><button class="icon-btn" data-action="close" aria-label="Close dialog">${icon('close')}</button></header><div class="modal-body">${content}</div></section></div>`;
  document.body.style.overflow = 'hidden';
  requestAnimationFrame(() => $('input:not([type=hidden]),select,textarea,button', $('.modal-body'))?.focus());
}
function closeModal() { $('#overlay').replaceChildren(); document.body.style.overflow = ''; state.chat = null; state.confirmation = null; state.modal?.isConnected && state.modal.focus(); state.modal = null; }
function requireAuth() { if (state.data.user) return true; showAuth(); return false; }
async function refresh() { state.data = await api('state'); render(); }
function navigate(route) {
  state.route = route;
  location.hash = '/' + route;
  render(); closeModal(); window.scrollTo({ top: 0, behavior: 'instant' });
}
function render() {
  const d = state.data, u = d.user;
  const unread = (d.notifications || []).filter(n => !n.read_at).length;
  const review = (d.exchanges || []).filter(x => x.status === 'pending' && x.seller_id === u?.id).length + (d.kits || []).filter(k => k.lines.some(l => l.supplier_id === u?.id && l.status === 'pending')).length;
  const nav = (label, route, symbol, count = 0) => `<button class="nav-item${state.route === route ? ' active' : ''}" data-action="nav" data-id="${route}" ${state.route === route ? 'aria-current="page"' : ''}>${icon(symbol)}<span>${label}</span>${count ? `<span class="nav-counter">${count}</span>` : ''}</button>`;
  $('#app').innerHTML = `<div class="shell"><aside class="sidebar" aria-label="Main navigation">
    <a class="brand" href="#/market" aria-label="EcoLoop home"><span class="brand-mark">${icon('loop')}</span>Eco<span>Loop</span></a><p class="brand-sub">Good things come around.</p>
    <div class="nav-group"><p class="nav-label">EXPLORE</p>${nav('Marketplace','market','market')}${nav('Project studio','projects','projects')}</div>
    <div class="nav-group secondary"><p class="nav-label">YOUR CORNER</p>${nav('My listings','listings','box')}${nav('Exchanges & requests','requests','exchange',review)}${nav('My credits','wallet','wallet')}${nav('Community impact','impact','leaf')}${u?.role === 'organiser' ? nav('Organiser desk','organiser','shield') : ''}</div>
    <div class="sidebar-bottom"><div class="side-note">${icon('spark')}<strong>A little less waste.<br>A lot more possibility.</strong><p>Your unused things could be someone’s next great idea.</p><button data-action="how">How EcoLoop works ${icon('arrow')}</button></div>
    <button class="profile-btn" data-action="${u ? 'profile' : 'auth'}"><span class="avatar">${e(initials(u?.name || 'Guest'))}</span><span class="profile-text"><strong>${e(u?.name || 'Make yourself at home')}</strong><span>${u ? e(u.role === 'organiser' ? 'Community organiser' : 'Community member') : 'Sign in or join your community'}</span></span>${icon('chevron')}</button></div></aside>
    <div class="main-wrap"><header class="topbar"><button class="mobile-menu icon-btn" data-action="menu" aria-label="Open navigation">${icon('menu')}</button><div class="community">${icon('pin')}<div><small>YOUR COMMUNITY</small><strong>${e(d.community)}</strong></div></div><div class="top-actions"><span class="top-caption">Small swaps. Bigger possibilities.</span><button class="icon-btn" data-action="notifications" aria-label="Notifications${unread ? ', '+unread+' unread' : ''}">${icon('bell')}${unread ? '<span class="notification-dot"></span>' : ''}</button>${button(u ? 'List an item' : 'Join the loop',u ? 'new-item' : 'auth','','primary','plus')}</div></header>
    ${d.demo ? `<div class="demo-banner"><strong>Demo workspace</strong><span>Sample listings · real exchange workflows · no money involved</span><button data-action="demo">${u ? 'Switch account' : 'Try a demo account'}</button></div>` : ''}
    <main id="main" tabindex="-1">${renderPage()}<footer class="page-footer"><span>${icon('leaf')}Made for a community that makes things last.</span><span>Trade what you have. Build what you need.</span></footer></main></div></div>`;
}
function pageHeading(kicker, title, description, action = '') { return `<div class="page-heading"><div><span class="eyebrow">${kicker}</span><h1>${title}</h1><p>${description}</p></div>${action}</div>`; }
function signedInPage(fn) { return state.data.user ? fn() : empty('Your corner of the loop', 'Sign in to manage your listings, projects, and exchanges.', button('Sign in or create an account','auth')); }
function renderPage() {
  if (state.route === 'projects') return projectsPage();
  if (state.route === 'listings') return signedInPage(listingsPage);
  if (state.route === 'requests') return signedInPage(requestsPage);
  if (state.route === 'wallet') return signedInPage(walletPage);
  if (state.route === 'impact') return impactPage();
  if (state.route === 'organiser') return state.data.user?.role === 'organiser' ? organiserPage() : empty('The organiser’s desk', 'This space is for your community’s approved organisers.');
  return marketplacePage();
}
function filteredItems() {
  const { q, category, mode } = state.filter;
  return state.data.items.filter(i => i.available > 0 && (category === 'All items' || i.category === category) && (mode === 'all' || (mode === 'shelf' ? !i.owner_id : i.mode === mode)) && (!q || `${i.title} ${i.description} ${i.material} ${i.owner_name}`.toLowerCase().includes(q.toLowerCase())));
}
function itemCard(i) {
  const price = i.mode === 'credits' ? `${i.credits}<small> cr / ${e(i.unit)}</small>` : i.mode === 'gift' ? 'A little gift' : 'Let’s trade';
  return `<button class="item-card" data-action="item" data-id="${e(i.id)}" aria-label="View ${e(i.title)}"><div class="item-art" data-material="${e(i.material)}"><span class="item-label">${!i.owner_id ? 'Community shelf' : i.mode === 'gift' ? 'Free to a good home' : e(state.data.conditions[i.condition])}</span>${i.image ? `<img src="${e(i.image)}" alt="${e(i.title)}" loading="lazy">` : illustration(i.material)}</div><div class="item-content"><h3 class="item-title">${e(i.title)}</h3><p class="item-sub">${e(i.available)} ${e(i.unit)}${i.available !== 1 ? 's' : ''} available · ${e(i.category)}</p><div class="item-footer"><span class="owner"><span class="avatar">${e(initials(i.owner_name))}</span><span>${e(i.owner_name)}</span></span><span class="value">${price}</span></div></div></button>`;
}
function marketGrid() { const items = filteredItems(); return items.length ? items.map(itemCard).join('') : empty('Nothing here just yet', 'Try another search or category. Your next good find could be one listing away.', button('List something useful','new-item','','secondary')); }
function marketplacePage() {
  return `<section class="hero"><div class="hero-copy"><div class="community-tag"><span class="dot"></span>A smaller footprint starts close to home</div><h1>Less new stuff.<br><em>More possibilities.</em></h1><p>Swap what you no longer need. Find materials for your next big idea. Give good things another chapter.</p><div class="hero-actions">${button('Build something new','nav','projects','primary','projects')}${button('How the loop works','how','','link','arrowUp')}</div></div><div class="hero-art"><div class="art-heading">Already here.<br>Ready for something new.</div><span class="art-mini">${icon('leaf')}</span>${illustration('organiser')}<span class="art-badge">${icon('loop')}Made with a little imagination.</span></div></section>
    <section class="how-strip" aria-label="How EcoLoop works"><div class="how-cell"><span class="how-icon">${icon('exchange')}</span><div><strong>Trade a little</strong><p>Useful things, new homes.</p></div></div><div class="how-cell"><span class="how-icon">${icon('projects')}</span><div><strong>Make something</strong><p>Find your project’s materials.</p></div></div><div class="how-cell"><span class="how-icon">${icon('leaf')}</span><div><strong>Keep it going</strong><p>Every contributor benefits.</p></div></div></section>
    <section><div class="section-head"><div><h2>Good finds, right around you.</h2><p>A community of useful things waiting for their next chapter.</p></div><span class="count-label" id="item-count">${filteredItems().length} available</span></div>
    <div class="filters" aria-label="Filter by category">${['All items',...state.data.categories].map(c => `<button class="filter-chip${state.filter.category === c ? ' active' : ''}" data-action="category" data-id="${e(c)}" aria-pressed="${state.filter.category === c}">${e(c)}</button>`).join('')}</div>
    <div class="search-row"><label class="search-field"><span class="sr-only">Search the marketplace</span>${icon('search')}<input id="market-search" type="search" placeholder="Try cardboard, books, art supplies…" value="${e(state.filter.q)}"></label><select id="mode-filter" aria-label="Exchange type">${options({all:'All exchanges',credits:'Trade credits',barter:'Direct barter',gift:'Gifts',shelf:'Community shelf'},state.filter.mode)}</select></div><div class="market-grid" id="market-grid">${marketGrid()}</div></section>`;
}
function updateMarket() { const grid = $('#market-grid'); if (grid) { grid.innerHTML = marketGrid(); $('#item-count').textContent = filteredItems().length + ' available'; } }
function projectsPage() {
  const d = state.data;
  const templates = d.templates.map(t => `<button class="template" data-action="template" data-id="${e(t.id)}"><div class="template-art">${illustration(t.art)}</div><div class="template-body"><span class="eyebrow">${e(t.category)}</span><h3>${e(t.name)}</h3><p>${e(t.description)}</p><div class="template-bottom"><span>${icon('clock')}${e(t.time)}</span><span>Start making ${icon('arrow')}</span></div></div></button>`).join('');
  const projects = (d.projects || []).map(p => `<article class="project-row"><div class="row-art">${illustration('organiser')}</div><div class="row-info">${badge(p.status)}<h3>${e(p.name)}</h3><p>${p.requirements.length} material requirements · ${p.needed_by ? 'Needed by ' + e(p.needed_by) : 'Created ' + formatDate(p.created_at)}</p></div><div class="row-actions">${['draft','cancelled'].includes(p.status) ? button('Find materials','match',p.id,'primary','search') : button(p.status === 'completed' ? 'View creation' : 'Open project','project-detail',p.id,'secondary','arrow')}</div></article>`).join('');
  return `${pageHeading('THE PROJECT STUDIO','A good idea deserves a second life.','Tell us what you want to make. We’ll help you find the materials already around you.',button('Create a project','new-project','','primary','plus'))}<div class="section-head"><div><h2>A little inspiration to get you going.</h2><p>Start with a simple template, or bring an idea of your own.</p></div></div><div class="template-grid">${templates}</div><div class="section-head"><h2>Your work in progress</h2><span class="count-label">${(d.projects || []).length} projects</span></div><div class="projects-list">${projects || empty('What will you make next?', 'Add a project name, describe your idea, and list what you need. We’ll take it from there.',button(d.user ? 'Start your first project' : 'Sign in to start','new-project','','secondary','plus'),'projects')}</div>`;
}
function listingsPage() {
  const mine = state.data.items.filter(i => i.owner_id === state.data.user.id);
  return `${pageHeading('YOUR LITTLE INVENTORY','Good things to pass on.','Keep your descriptions honest and your quantities up to date. Someone nearby might need exactly this.',button('List an item','new-item','','primary','plus'))}<div class="market-grid">${mine.map(itemCard).join('') || empty('Room for a fresh start', 'An unused book, an extra notebook, a useful leftover. Add your first listing.',button('Add a listing','new-item','','primary','plus'))}</div>`;
}
function exchangeRow(x) {
  const u = state.data.user, mine = x.buyer_id === u.id;
  return `<article class="request-row"><div class="row-art">${icon('exchange')}</div><div class="row-info">${badge(x.status)} <span class="small muted">${x.mode === 'credits' ? x.total+' credits' : pretty(x.mode)}</span><h3>${e(x.item.title)}</h3><p>${mine ? 'Requested from ' + e(x.seller_name) : 'Requested by ' + e(x.buyer_name)} · ${x.quantity} ${e(x.item.unit)}${x.offered ? ' · In return: ' + e(x.offered.title) : ''}</p></div><div class="row-actions">${button('View exchange','exchange',x.id,'secondary','arrow')}</div></article>`;
}
function kitRow(k) { return `<article class="request-row"><div class="row-art">${icon('projects')}</div><div class="row-info">${badge(k.status)} <span class="small muted">Project kit</span><h3>${e(k.name)}</h3><p>For ${e(k.maker_name)} · ${k.lines.length} material matches · ${k.total} credits</p></div><div class="row-actions">${button('View kit','kit',k.id,'secondary','arrow')}</div></article>`; }
function requestsPage() {
  const d = state.data, rows = [...d.kits.map(k => ({ created: k.created_at, html: kitRow(k) })), ...d.exchanges.map(x => ({created:x.created_at,html:exchangeRow(x)}))].sort((a,b) => b.created-a.created);
  return `${pageHeading('CONNECTIONS THAT COUNT','A little give. A little get.','Review requests, talk through the details, and confirm when the handover is complete.')}<div class="request-list">${rows.map(r => r.html).join('') || empty('Your next exchange starts with a hello', 'Browse the marketplace or request the materials for a project.',button('Explore the marketplace','nav','market','secondary'),'exchange')}</div>`;
}
function contributionCard(c) { return `<article class="contribution-card">${badge(c.status)}<h3>${e(c.contributor_name)} · ${c.total} credits</h3><p>Submitted ${fullDate(c.created_at)}</p><ul>${c.items.map(i => `<li>${e(i.title)} · ${i.quantity} ${e(i.unit)} · ${i.quantity*i.credits} credits</li>`).join('')}</ul>${c.note ? `<p>${e(c.note)}</p>` : ''}${state.data.user.role === 'organiser' && c.status === 'pending' ? button('Review contribution','review-contribution',c.id,'primary','shield') : state.data.user.role === 'organiser' && c.status === 'accepted' ? button('View contribution','review-contribution',c.id,'secondary') : ''}</article>`; }
function walletPage() {
  const d = state.data, w = d.wallet;
  const ledger = d.ledger.map(l => `<div class="ledger-row">${icon(l.amount >= 0 ? 'plus' : 'arrow')}<div class="row-info"><h3>${e(l.description)}</h3><p>${e(pretty(l.kind))} · ${fullDate(l.created_at)}</p></div><span class="ledger-amount${l.amount < 0 ? ' negative' : ''}">${l.amount > 0 ? '+' : ''}${l.amount}</span></div>`).join('');
  return `${pageHeading('VALUE THAT GOES AROUND','Your next good find is in reach.','Earn credits by contributing useful things. Spend them on participating listings or your next project kit.',button('Contribute items','contribute','','primary','plus'))}<div class="wallet-grid"><div class="stat-card"><small>AVAILABLE TO USE</small><div class="number">${w.available}</div><p>Trade credits · no cash value</p></div><div class="stat-card"><small>HELD FOR REQUESTS</small><div class="number">${w.held}</div><p>Reserved until collection or cancellation</p></div><div class="stat-card"><small>TOTAL BALANCE</small><div class="number">${w.total}</div><p>Available credits + active holds</p></div></div><div class="two-col"><section class="panel"><h2>A record of your exchanges</h2>${ledger || `<p class="text-block">Your credit history will appear here after an organiser accepts a contribution or someone completes an exchange with you.</p>`}${d.holds.length ? `<div class="form-section"><h3>Active holds</h3>${d.holds.map(h => `<div class="summary-line"><span>Request ${e(h.reference.slice(0,8))}</span><strong>${h.amount} credits</strong></div>`).join('')}</div>` : ''}</section><aside class="panel"><h2>A simple way to keep things fair.</h2><div class="mini-steps"><div class="mini-step"><span class="step-number">1</span><div><h3>Bring something useful</h3><p>Submit your items, then bring them to the exchange desk for a condition and value check.</p></div></div><div class="mini-step"><span class="step-number">2</span><div><h3>Receive your credits</h3><p>Credits arrive only after physical acceptance. A listing on its own earns no credits.</p></div></div><div class="mini-step"><span class="step-number">3</span><div><h3>Choose what you need</h3><p>Get a project kit or choose something from the marketplace. Its owner earns your credits.</p></div></div></div></aside></div><div class="section-head space-top"><h2>Your contributions</h2></div><div class="organiser-grid">${d.contributions.filter(c => c.user_id === d.user.id).map(contributionCard).join('') || `<p class="text-block">No contributions yet. Your unused things can be the start of something good.</p>`}</div>`;
}
function impactPage() {
  const i = state.data.impact;
  return `${pageHeading('SMALL ACTIONS, SHARED PROGRESS','Good things are going around.','A record of completed activity in your community. Every number below comes from an actual recorded action.')}<div class="impact-grid">${[
    ['exchange',i.exchanges,'Completed exchanges','Confirmed by both participants'],['projects',i.kits,'Project kits collected','Whole kits handed over to their makers'],['leaf',i.projects,'Finished projects','Results documented by their makers'],['coins',i.supplier_credits,'Credits earned by suppliers','Value received for project materials'],['wallet',i.redeemed_credits,'Credits redeemed at the shelf','Shared stock selected by a recipient'],['user',i.members,'Community members','People taking part in this shared space'],
  ].map(([symbol,value,label,detail]) => `<div class="stat-card">${icon(symbol)}<small>${label.toUpperCase()}</small><div class="number">${value}</div><p>${detail}</p></div>`).join('')}</div><div class="note">${state.data.demo ? 'This is a demo workspace. Activity here demonstrates the workflow; it is not a real-world environmental result. ' : ''}Listings are not counted as completed reuse. We do not estimate carbon savings or claim that every exchanged item avoided a new purchase.</div><div class="two-col space-top"><section class="panel"><h2>Ideas looking for materials</h2>${state.data.wants.map(w => `<article class="wanted"><h3>${e(w.title)}</h3><p>${e(w.description)}</p><small class="muted">Requested by ${e(w.maker_name)}</small></article>`).join('') || `<p class="text-block">Missing materials from a project can be shared here so the community knows what would be useful.</p>`}</section><section class="panel"><h2>Made in the community</h2>${state.data.showcases.map(p => `<article class="wanted">${p.completed_image ? `<img class="completion-photo" src="${e(p.completed_image)}" alt="${e(p.name)}">` : ''}<h3>${e(p.name)}</h3><p>${e(p.completed_note)}</p><small class="muted">By ${e(p.maker_name)} · ${formatDate(p.completed_at)} · maker-reported</small></article>`).join('') || `<p class="text-block">Completed projects appear here when their makers choose to share them. A little inspiration can go a long way.</p>`}</section></div>`;
}
function organiserPage() {
  const d = state.data, r = d.reconciliation;
  return `${pageHeading('COMMUNITY OPERATIONS','Keep the loop running smoothly.','Accept useful goods, check in project materials, and make sure every credit has stock behind it.')}<section class="reconciliation"><div><p>SHARED-STOCK VALUE</p><strong>${r.stock}</strong></div><div><p>OUTSTANDING CREDITS</p><strong>${r.outstanding}</strong></div><div><p>RECONCILIATION</p><strong>${r.balanced ? 'Balanced' : 'Review needed'}</strong></div>${button(r.paused ? 'Resume intake' : 'Pause intake','toggle-issuance','','secondary',r.paused ? 'check' : 'shield')}</section>${r.paused ? '<p class="note warning space-bottom">New credit issuance is paused. Existing exchanges can continue.</p>' : ''}<div class="section-head"><h2>Contributions to review</h2><span class="count-label">${d.contributions.filter(c => c.status==='pending').length} pending</span></div><div class="organiser-grid">${d.contributions.filter(c => c.status==='pending').map(contributionCard).join('') || '<p class="text-block">All caught up. New contribution offers will appear here.</p>'}</div><div class="section-head"><h2>At the collection desk</h2></div><div class="request-list">${d.kits.filter(k => ['pending','approved','ready'].includes(k.status) || k.lines.some(l => l.status==='return_due')).map(kitRow).join('') || '<p class="text-block">No kits are awaiting collection or return.</p>'}${d.exchanges.filter(x => !x.seller_id && ['pending','accepted'].includes(x.status)).map(exchangeRow).join('')}</div><div class="section-head space-top"><h2>Accepted contributions</h2></div><div class="organiser-grid">${d.contributions.filter(c => c.status==='accepted').map(contributionCard).join('') || '<p class="text-block">Accepted contributions will appear here with their original agreed values.</p>'}</div>`;
}

function showAuth(mode = 'login') {
  const registration = mode === 'register';
  openModal(registration ? 'A good place to start.' : 'Welcome back to the loop.', 'Useful things. New possibilities. Your community is right here.', `<div class="auth-tabs"><button class="${!registration ? 'active' : ''}" data-action="auth">Sign in</button><button class="${registration ? 'active' : ''}" data-action="register">Join the community</button></div><form data-form="${mode}" class="form-grid">${registration ? field('Your name',input('name','','text','required minlength="2" maxlength="60" autocomplete="name"'),true) : ''}${field('Email address',input('email','','email','required maxlength="180" autocomplete="username"'),true)}${field('Password',input('password','','password',`required minlength="${registration ? 10 : 1}" maxlength="200" autocomplete="${registration ? 'new-password' : 'current-password'}"`),true,registration ? 'At least 10 characters. Use a password you do not use elsewhere.' : '')}<div class="full field"><p class="form-error" role="alert"></p><div class="form-actions">${button(registration ? 'Create my account' : 'Sign in','submit','','primary','arrow')}</div></div></form>${state.data.demo ? '<p class="auth-foot">Just exploring? <button data-action="demo">Try one of the demo accounts.</button></p>' : ''}`);
  $('.form-actions button').type = 'submit';
}
function showDemo() {
  const roles = { 'Pratyush':'Project maker · contribute items and build a kit', 'Asha':'Material owner · cardboard and books', 'Kabir':'Material owner · tubes and stationery', 'Mira':'Material owner · fabric and craft supplies', 'Community Organiser':'Organiser · approve contributions and collection' };
  openModal('Try every side of the loop.', 'These are fictional demo accounts. Switch between people to complete the entire exchange.', `<div class="demo-accounts">${state.data.demo_users.map(u => `<button class="demo-account" data-action="demo-login" data-id="${e(u.id)}"><span class="avatar">${e(initials(u.name))}</span><span><strong>${e(u.name)}</strong><small>${e(roles[u.name] || pretty(u.role))}</small></span>${icon('arrow')}</button>`).join('')}</div><p class="note space-top">Start as Pratyush. Submit a contribution, switch to the organiser to accept it, then return as Pratyush to request a project kit.</p>`);
}
function showHow() {
  openModal('Good things come around.', 'One community. Two ways to make more of what is already here.', `<div class="mini-steps"><div class="mini-step"><span class="step-number">1</span><div><h3>Trade what you have</h3><p>List unused belongings for direct barter, trade credits, or a gift. Owners approve each request and both people confirm the handover.</p></div></div><div class="mini-step"><span class="step-number">2</span><div><h3>Build what you need</h3><p>Add a project and its materials. EcoLoop checks real listings for compatible quantities and dimensions, then proposes a kit from several owners.</p></div></div><div class="mini-step"><span class="step-number">3</span><div><h3>Give back equivalent value</h3><p>Bring useful items to the community shelf. After an organiser checks and accepts them, your credits can fund a kit or another exchange.</p></div></div><div class="mini-step"><span class="step-number">4</span><div><h3>Everyone gets a choice</h3><p>Material owners earn your credits when the kit is collected. They can choose different goods they actually want. Credits have no cash value.</p></div></div></div><div class="note space-top">Collection point: ${e(state.data.collection)}. Always arrange a handover with the other participants first.</div>`);
}
function itemFields(data = {}, contribution = false) {
  const d = state.data;
  return `<div class="item-fields" data-existing-image="${e(data.image || '')}"><div class="form-grid">${field('Item name',input('title',data.title || '','text','required minlength="3" maxlength="100" placeholder="e.g. Sturdy cardboard sheets · pack of 3"'),true)}${field('Tell people about it',area('description',data.description || '','required minlength="8" maxlength="2500" placeholder="Condition, what is included, and anything the next owner should know."'),true)}${field('Category',select('category',d.categories,data.category || 'Materials'))}${field('Material / item type',select('material',d.materials,data.material || 'cardboard_sheet'))}${field('Condition',select('condition',d.conditions,data.condition || 'good'))}${field('Unit',select('unit',d.units,data.unit || 'piece'))}${field('Quantity',input('quantity',data.quantity || 1,'number','required min="1" max="1000" step="1"'))}${field('Credits per unit',input('credits',data.credits ?? 10,'number','min="0" max="1000" step="1" required'),false,'A proposed exchange value. Not a rupee price.')}${!contribution ? field('How would you like to exchange?',select('mode',{credits:'Trade credits',barter:'Direct barter',gift:'Give as a gift'},data.mode || 'credits')) : ''}${field('Available from',input('available_by',data.available_by || new Date().toISOString().slice(0,10),'date','required'))}${field('Photo (optional)',input('photo','','file','accept="image/png,image/jpeg,image/webp"'),true,'JPEG, PNG, or WebP, under 2 MB. Your listing will use an illustration if no photo is added.')}</div><details class="space-top"><summary>Dimensions for precise project matches</summary><div class="form-grid three">${field('Width (cm)',input('width',data.width || 0,'number','min="0" max="10000" step="0.1"'))}${field('Height / length (cm)',input('height',data.height || 0,'number','min="0" max="10000" step="0.1"'))}${field('Thickness (mm)',input('thickness',data.thickness || 0,'number','min="0" max="1000" step="0.1"'))}</div></details><label class="check-field space-top"><input type="checkbox" name="project_eligible" ${data.project_eligible !== 0 ? 'checked' : ''}>Consider this listing for project material requests.</label></div>`;
}
function showItemForm(itemId = null) {
  if (!requireAuth()) return;
  const item = itemId ? state.data.items.find(i => i.id === itemId) : {};
  openModal(itemId ? 'A little update.' : 'Give something a next chapter.', 'Clear descriptions make good exchanges. You stay in control of who receives your item.', `<form data-form="item" data-id="${e(itemId || '')}">${itemFields(item)}<p class="form-error" role="alert"></p><div class="form-actions"><button type="button" class="btn secondary" data-action="close">Cancel</button><button type="submit" class="btn primary">${itemId ? 'Save changes' : 'Publish listing'} ${icon('arrow')}</button></div></form>`);
}
function contributionFormItem(data = {}, index = 1) { return `<section class="contribution-form-item"><div class="req-head"><span>CONTRIBUTION ITEM ${index}</span>${index > 1 ? '<button type="button" data-action="remove-contribution">Remove</button>' : ''}</div>${itemFields(data, true)}</section>`; }
function showContribution() {
  if (!requireAuth()) return;
  openModal('Bring value back into the loop.', 'Offer useful belongings to the community shelf. An organiser will agree the value and inspect the items before credits are issued.', `<form data-form="contribution"><div class="note space-bottom">Submitting this form earns no credits yet. Bring the accepted items to ${e(state.data.collection)}. Only contribute goods you own.</div><div id="contribution-items">${contributionFormItem()}</div><button type="button" class="btn secondary" data-action="add-contribution">${icon('plus')}Add another item</button><p class="form-error" role="alert"></p><div class="form-actions"><button class="btn primary" type="submit">Submit for review ${icon('arrow')}</button></div></form>`,true);
}
function requirementForm(r = {}, index = 1) {
  return `<section class="req-form"><div class="req-head"><span>MATERIAL ${index}</span><button type="button" data-action="remove-requirement">Remove</button></div><div class="form-grid">${field('What do you need?',input('title',r.title || '','text','required minlength="2" maxlength="100" placeholder="e.g. Cardboard sheets · pack of 3"'),true)}${field('Material type',select('material',state.data.materials,r.material || 'cardboard_sheet'))}${field('Unit',select('unit',state.data.units,r.unit || 'piece'))}${field('Quantity needed',input('quantity',r.quantity || 1,'number','required min="1" max="1000"'))}${field('Minimum condition',select('condition',state.data.conditions,r.condition || 'fair'))}</div><details><summary>Dimensions and approved alternatives</summary><div class="form-grid three">${field('Min. width (cm)',input('width',r.width || 0,'number','min="0" max="10000" step="0.1"'))}${field('Min. height (cm)',input('height',r.height || 0,'number','min="0" max="10000" step="0.1"'))}${field('Min. thickness (mm)',input('thickness',r.thickness || 0,'number','min="0" max="1000" step="0.1"'))}${field('Alternative material',select('alternative',{'':'No alternative',...state.data.materials},r.alternative || ''),true)}</div><label class="check-field space-top"><input name="approve_alternative" type="checkbox" ${r.approve_alternative ? 'checked' : ''}>I approve using this alternative if the exact material is unavailable.</label></details></section>`;
}
function showProjectForm(templateId = null, projectId = null) {
  if (!requireAuth()) return;
  const template = state.data.templates.find(t => t.id === templateId);
  const p = state.data.projects.find(p => p.id === projectId) || (template ? { ...template, name: template.project_name } : {});
  openModal(projectId ? 'Refine your idea.' : 'What are you making?', 'Describe your project, then tell us exactly what you need. You can review every match before requesting a kit.', `<form data-form="project" data-id="${e(projectId || '')}"><div class="form-grid">${field('Project name',input('name',p.name || '','text','required minlength="3" maxlength="100" placeholder="A desktop organiser, an exhibition model…"'),true)}${field('Describe your idea',area('description',p.description || '','required minlength="8" maxlength="3000" placeholder="What will you make, and what should it do?"'),true)}${field('Already have',input('already_have',p.already_have || '','text','maxlength="1000" placeholder="e.g. scissors, glue, ruler"'))}${field('Needed by (optional)',input('needed_by',p.needed_by || '','date'))}</div><div class="form-section"><h3>Your material checklist</h3><div id="requirements">${(p.requirements || [{}]).map((r,i) => requirementForm(r,i+1)).join('')}</div><button type="button" class="btn secondary" data-action="add-requirement">${icon('plus')}Add a material</button></div><p class="form-error" role="alert"></p><div class="form-actions"><button type="button" class="btn secondary" data-action="close">Keep thinking</button><button type="submit" class="btn primary">Find my materials ${icon('search')}</button></div></form>`,true);
}
async function showMatch(projectId) {
  if (!requireAuth()) return;
  const m = await api('matches/' + projectId); state.match = m;
  const canAfford = m.wallet.available >= m.total;
  openModal(e(m.project.name), 'Your idea, matched to real things in your community.', `<div class="match-layout"><div>${m.requirements.map(r => `<article class="match-req">${badge(r.missing ? 'missing' : 'matched')}<h3>${e(r.title)}</h3><p>${r.quantity} ${e(r.unit)} required${r.width || r.height ? ` · minimum ${r.width} × ${r.height} cm` : ''}</p>${r.lines.map(l => `<div class="match-line"><div class="row-art">${icon('box')}</div><div><strong>${e(l.title)}${l.alternative ? ' · approved alternative' : ''}</strong><small>${l.quantity} ${e(l.unit)} · ${e(l.owner_name)}</small></div><span class="cost">${l.cost} cr</span></div>`).join('')}${r.missing ? `<p class="note warning space-top">Still needed: ${r.missing} ${e(r.unit)}. ${r.alternatives.length ? 'An alternative exists. Edit the project to approve it explicitly.' : 'Try adjusting the requirement or let your community know.'}</p>` : ''}</article>`).join('')}<div class="row-actions">${button('Edit requirements','edit-project',projectId,'secondary','edit')}${!m.complete ? button('Ask the community','wanted',projectId,'soft','chat') : ''}</div></div><aside class="match-summary"><h3>Your proposed project kit</h3><div class="summary-line"><span>Material requirements</span><strong>${m.requirements.length}</strong></div><div class="summary-line"><span>Contributors</span><strong>${m.owners}</strong></div><div class="summary-line total"><span>Kit value</span><strong>${m.total} credits</strong></div><div class="summary-line"><span>Your available credits</span><strong>${m.wallet.available}</strong></div>${!canAfford ? `<div class="note warning">You need ${m.total-m.wallet.available} more credits to fund this kit.</div>${button('Contribute useful items','contribute','','secondary block','plus')}` : ''}<button class="btn primary block" data-action="request-kit" data-id="${e(projectId)}" ${!m.complete || !canAfford ? 'disabled' : ''}>${icon('projects')}Request this kit</button><p>Materials and credits are held for up to 48 hours while owners approve. Owners are paid only after the complete kit is checked in and you collect it.</p><p>${e(state.data.collection)}</p></aside></div>`,true);
}
function showItem(itemId) {
  const i = state.data.items.find(i => i.id === itemId);
  if (!i) return toast('That item is no longer available.', true);
  const own = i.owner_id === state.data.user?.id;
  const mine = state.data.items.filter(x => x.owner_id === state.data.user?.id && x.available > 0);
  const dims = i.width || i.height ? `${i.width} × ${i.height} cm${i.thickness ? ' · '+i.thickness+' mm' : ''}` : 'Ask the owner';
  openModal(e(i.title), `${e(i.owner_name)} · ${e(i.community)}`, `<div class="item-detail-art">${i.image ? `<img src="${e(i.image)}" alt="${e(i.title)}">` : illustration(i.material)}</div><p class="item-detail-desc">${e(i.description)}</p><div class="detail-meta">${badge(i.condition)}${badge(i.mode)}${i.project_eligible ? '<span class="badge">Available for projects</span>' : ''}${!i.owner_id ? '<span class="badge">Verified shelf intake</span>' : ''}</div><div class="detail-stats"><div class="detail-stat"><small>AVAILABLE</small><strong>${i.available} ${e(i.unit)}</strong></div><div class="detail-stat"><small>DIMENSIONS</small><strong>${e(dims)}</strong></div><div class="detail-stat"><small>EXCHANGE VALUE</small><strong>${i.mode === 'credits' ? i.credits+' credits / '+e(i.unit) : i.mode==='gift' ? 'A free gift' : 'Agreed barter'}</strong></div></div>${i.source_project_id ? '<div class="note space-bottom">This listing continues the story of a project made in EcoLoop.</div>' : ''}${own ? `<div class="form-actions">${button('Archive listing','archive-item',i.id,'secondary')}${!i.source_id ? button('Edit listing','edit-item',i.id,'primary','edit') : ''}</div>` : !state.data.user ? button('Sign in to request','auth','','primary block','arrow') : `<form data-form="exchange-request" data-id="${e(i.id)}"><div class="form-grid">${field('Quantity to request',input('quantity',1,'number',`required min="1" max="${i.available}"`))}${i.mode === 'barter' ? field('Your item to offer',select('offered_id',Object.fromEntries(mine.map(x => [x.id,x.title+' ('+x.available+' available)'])),mine[0]?.id)) : `<div class="note">${i.mode === 'credits' ? 'Credits are held when you request. Payment happens after both people confirm handover.' : 'The owner will approve your request before you arrange collection.'}</div>`}${i.mode === 'barter' ? field('Quantity you are offering',input('offered_quantity',1,'number','required min="1" max="1000"')) : ''}</div><p class="form-error" role="alert"></p><div class="form-actions"><button class="btn primary" type="submit" ${i.available<1 || (i.mode==='barter'&&!mine.length) ? 'disabled' : ''}>Send request ${icon('arrow')}</button></div>${i.mode==='barter'&&!mine.length ? '<p class="note warning">List something you own before proposing a barter.</p>' : ''}</form>`}`);
}
function kitProgress(status) { const stage = {pending:0,approved:1,ready:2,collected:3}[status] ?? -1; return `<div class="kit-progress">${['Owners approve','Desk checks materials','Ready for collection','Kit collected'].map((label,i) => `<div class="kit-step${i <= stage ? ' done' : ''}"><span>${i < stage ? icon('check') : i+1}</span>${label}</div>`).join('')}</div>`; }
function showKit(kitId) {
  const k = state.data.kits.find(k => k.id===kitId), u = state.data.user;
  if (!k) return toast('Kit not found.',true);
  const own = k.user_id === u.id, active = ['pending','approved','ready'].includes(k.status);
  const approve = k.lines.some(l => l.supplier_id===u.id && l.status==='pending');
  openModal(e(k.name), `A project kit for ${e(k.maker_name)} · ${k.total} credits · ${e(pretty(k.status))}`, `${kitProgress(k.status)}${k.lines.map(l => `<article class="kit-line"><div class="row-art">${illustration(l.material)}</div><div class="row-info"><h3>${e(l.title)}</h3><p>${l.quantity} ${e(l.unit)} · ${e(l.supplier_name)}</p><div class="space-top">${badge(l.status)}</div></div><span class="value">${l.cost} cr</span>${u.role==='organiser'&&l.status==='accepted' ? button('Check in','receive-line',l.id,'secondary compact') : ''}${u.role==='organiser'&&l.status==='return_due' ? button('Confirm returned','return-line',l.id,'secondary compact') : ''}</article>`).join('')}<div class="note collection-note">${icon('pin')}<div><strong>${e(state.data.collection)}</strong><br>${active ? 'Reservation ends '+fullDate(k.expires_at)+'. Owners are paid after the whole kit is collected.' : k.status==='collected' ? 'Your credit transfer and material handover are recorded.' : 'Held credits have been released. Any materials already at the desk must be returned to their owners.'}</div></div><div class="row-actions">${button('Group conversation','chat-kit',k.id,'secondary','chat')}${approve ? button('Approve my materials','approve-kit',k.id,'primary','check') : ''}${own&&k.status==='ready' ? button('Confirm kit collected','collect-kit',k.id,'primary','check') : ''}${own&&k.status==='collected'&&state.data.projects.find(p=>p.id===k.project_id)?.status==='building' ? button('Record finished project','complete-project',k.project_id,'primary','leaf') : ''}${active ? button('Cancel kit','cancel-kit',k.id,'danger') : ''}</div>`,true);
  $('.modal').dataset.kitId = k.id;
}
function showExchange(exchangeId) {
  const x = state.data.exchanges.find(x => x.id===exchangeId), u = state.data.user;
  if (!x) return toast('Exchange not found.',true);
  const buyer = x.buyer_id===u.id, seller = x.seller_id===u.id || (!x.seller_id&&u.role==='organiser');
  const confirmed = buyer ? x.buyer_confirmed : x.seller_confirmed;
  openModal(e(x.item.title), `Between ${e(x.buyer_name)} and ${e(x.seller_name)}`, `<div class="detail-meta">${badge(x.status)}${badge(x.mode)}</div><div class="detail-stats"><div class="detail-stat"><small>REQUESTED</small><strong>${x.quantity} ${e(x.item.unit)}</strong></div><div class="detail-stat"><small>IN RETURN</small><strong>${x.mode==='credits' ? x.total+' credits' : x.mode==='barter' ? e(x.offered_quantity+' '+x.offered.unit) : 'A thank-you'}</strong></div><div class="detail-stat"><small>CREATED</small><strong>${formatDate(x.created_at)}</strong></div></div>${x.offered ? `<p class="note">Offered in return: ${e(x.offered.title)}. Confirm its condition and quantity before accepting.</p>` : ''}<div class="note space-top"><strong>${e(state.data.collection)}</strong><br>Arrange collection in the conversation. Each participant confirms their own handover; credits transfer only after both confirmations.</div><div class="detail-meta"><span class="badge">${e(x.buyer_name)}: ${x.buyer_confirmed ? 'confirmed' : 'not confirmed'}</span><span class="badge">${e(x.seller_name)}: ${x.seller_confirmed ? 'confirmed' : 'not confirmed'}</span></div><div class="row-actions">${button('Conversation','chat-exchange',x.id,'secondary','chat')}${seller&&x.status==='pending' ? button('Accept request','accept-exchange',x.id,'primary','check') : ''}${x.status==='accepted'&&!confirmed ? button('Confirm handover','confirm-exchange',x.id,'primary','check') : ''}${['pending','accepted'].includes(x.status)&&!x.buyer_confirmed&&!x.seller_confirmed ? button('Cancel request','cancel-exchange',x.id,'danger') : ''}</div>`);
}
function showComplete(projectId) {
  const p = state.data.projects.find(p => p.id===projectId), k = state.data.kits.find(k => k.project_id===projectId&&k.status==='collected');
  if (!p || !k) return;
  openModal('Look what you made.', 'Record the result and the materials you actually used. Usable leftovers will become listings in your name.', `<form data-form="complete-project" data-id="${e(p.id)}"><div class="form-grid">${field('How did it turn out?',area('note','','required minlength="8" maxlength="2000" placeholder="Describe what you made and how it will be used."'),true)}${field('A photo of your finished project (optional)',input('photo','','file','accept="image/png,image/jpeg,image/webp"'),true)}</div><div class="form-section"><h3>Materials actually used</h3>${k.lines.map(l => field(`${e(l.title)} · received ${l.quantity} ${e(l.unit)}`,input('used_'+l.id,l.quantity,'number',`required min="0" max="${l.quantity}" step="1"`))).join('<br>')}</div><label class="check-field space-top"><input name="shared" type="checkbox">Share my finished project and display name with the community.</label><p class="note space-top">This records your project outcome. Completing a project does not create extra trade credits.</p><p class="form-error" role="alert"></p><div class="form-actions"><button type="submit" class="btn primary">Complete project ${icon('leaf')}</button></div></form>`);
}
function showProjectDetail(projectId) {
  const p = state.data.projects.find(p => p.id===projectId), k = state.data.kits.find(k => k.project_id===projectId&&k.status==='collected') || state.data.kits.find(k=>k.project_id===projectId);
  if (p.status !== 'completed') return k ? showKit(k.id) : showMatch(p.id);
  openModal(e(p.name),'A finished project. A new chapter for its materials.',`${p.completed_image ? `<img class="completion-photo space-bottom" src="${e(p.completed_image)}" alt="${e(p.name)}">` : `<div class="item-detail-art">${illustration('organiser')}</div>`}<p class="item-detail-desc">${e(p.completed_note)}</p><div class="detail-meta">${badge('completed')}<span class="badge">Maker-reported · ${formatDate(p.completed_at)}</span></div><p class="note">Any unused material has been relisted with its source history. You can keep your creation or offer it in another exchange.</p><div class="form-actions">${button('View material history','kit',k.id,'secondary')}${button('List my creation','relist-project',p.id,'primary','plus')}</div>`);
}
function showRelist(projectId) {
  const p = state.data.projects.find(p => p.id===projectId);
  openModal('Pass your creation on.', 'Its listing will keep a link to the project it came from.', `<form data-form="relist" data-id="${e(p.id)}">${itemFields({title:p.name,description:p.completed_note,image:p.completed_image,category:'Made by you',material:'other',quantity:1})}<p class="form-error" role="alert"></p><div class="form-actions"><button class="btn primary" type="submit">Publish creation ${icon('arrow')}</button></div></form>`);
}
function showReview(contributionId) {
  const c = state.data.contributions.find(c=>c.id===contributionId);
  const itemInfo = c.items.map(i=>`<article class="match-req"><h3>${e(i.title)}</h3><p>${e(i.description)}</p><div class="detail-meta">${badge(i.condition)}<span class="badge">${i.quantity} ${e(i.unit)}</span><span class="badge">${i.credits} credits / ${e(i.unit)}</span></div>${i.image ? `<img class="completion-photo" src="${e(i.image)}" alt="${e(i.title)}">` : ''}</article>`).join('');
  openModal('A contribution to the community.',`${e(c.contributor_name)} · ${c.total} proposed credits · ${e(pretty(c.status))}`,`${itemInfo}${c.status==='pending' ? `<form data-form="review" data-id="${e(c.id)}">${field('Your decision',select('decision',{accept:'Accept the original proposed value',decline:'Decline and leave a note'},'accept'))}<br>${field('Note for the contributor',area('note','','maxlength="1000" placeholder="Condition check, agreed pickup instructions, or reason for declining."'))}<label class="check-field space-top"><input type="checkbox" name="physically_received">I have physically received these goods, checked their condition and local usefulness, and agree to the proposed value.</label><p class="note space-top">Accepting issues ${c.total} credits and places the goods in shared stock. You cannot approve your own contribution.</p><p class="form-error" role="alert"></p><div class="form-actions"><button type="submit" class="btn primary">Save review ${icon('shield')}</button></div></form>` : `<p class="note space-top">A full reversal requires all original goods to remain available and the contributor to have all ${c.total} credits available. Return the goods physically when you reverse the contribution.</p><div class="form-actions">${button('Return & reverse contribution','reverse-contribution',c.id,'danger')}</div>`}`);
}
function confirmAction(title, description, label, callback) {
  openModal(title,'Please check the details before confirming.',`<form data-form="confirmation"><p class="text-block">${description}</p><label class="check-field space-top"><input type="checkbox" required>I have checked this and want to continue.</label><p class="form-error" role="alert"></p><div class="form-actions"><button type="button" class="btn secondary" data-action="close">Go back</button><button type="submit" class="btn primary">${label}</button></div></form>`);
  state.confirmation = callback;
}
async function showChat(kind, ref) {
  openModal(kind==='kit' ? 'Project conversation' : 'Exchange conversation',kind==='kit' ? 'Visible to the maker, all material suppliers, and community organisers.' : 'Visible only to the participants; shelf exchanges include the organisers.',`<div class="chat-messages" id="chat-messages"></div><form data-form="chat" data-kind="${kind}" data-id="${e(ref)}" class="chat-form"><label class="sr-only" for="message-body">Your message</label><textarea id="message-body" name="body" required maxlength="1500" placeholder="Arrange a pickup, ask about a material…"></textarea><button type="submit" class="btn primary" aria-label="Send message">${icon('arrow')}</button><p class="form-error" role="alert"></p></form>`);
  state.chat = {kind,ref}; await refreshChat();
}
async function refreshChat() {
  const chat = state.chat; if (!chat) return;
  const {messages} = await api(`messages?kind=${chat.kind}&reference=${encodeURIComponent(chat.ref)}`);
  if (state.chat !== chat || !$('#chat-messages')) return;
  const target = $('#chat-messages'), nearBottom = target.scrollHeight-target.scrollTop-target.clientHeight<70;
  target.innerHTML = messages.map(m=>`<article class="message${m.user_id===state.data.user.id ? ' mine' : ''}"><strong>${e(m.name)}</strong><p>${e(m.body)}</p><small>${fullDate(m.created_at)}</small></article>`).join('') || '<p class="text-block">Start a conversation to make the handover easy.</p>';
  if (nearBottom) target.scrollTop = target.scrollHeight;
}

async function photoFrom(inputElement) {
  const file = inputElement?.files?.[0];
  if (!file) return '';
  if (file.size > 2*1024*1024) throw new Error('Use a photo smaller than 2 MB.');
  const data = await new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(r.result);r.onerror=()=>reject(new Error('Could not read the photo.'));r.readAsDataURL(file);});
  return (await api('uploads',{data})).url;
}
function values(root) {
  const data = {};
  for (const el of $$('input[name],select[name],textarea[name]',root)) {
    if (el.type==='file') continue;
    data[el.name] = el.type==='checkbox' ? el.checked : el.type==='number' ? Number(el.value) : el.value;
  }
  return data;
}
async function readItemFields(root) { return {...values(root),image:await photoFrom($('input[type=file]',root)) || root.dataset.existingImage || ''}; }
const actions = {
  close:closeModal, how:showHow, auth:()=>showAuth('login'), register:()=>showAuth('register'), demo:showDemo,
  nav:el=>navigate(el.dataset.id),
  menu:()=>$('.sidebar').classList.toggle('open'),
  category:el=>{state.filter.category=el.dataset.id;$$('.filter-chip').forEach(b=>{b.classList.toggle('active',b===el);b.setAttribute('aria-pressed',b===el?'true':'false');});updateMarket();},
  'new-item':()=>showItemForm(), 'edit-item':el=>showItemForm(el.dataset.id), item:el=>showItem(el.dataset.id),
  'new-project':()=>showProjectForm(), 'edit-project':el=>showProjectForm(null,el.dataset.id),
  template:el=>{const t=state.data.templates.find(t=>t.id===el.dataset.id);openModal(e(t.name),e(t.description),`<div class="item-detail-art">${illustration(t.art)}</div><div class="text-block"><strong>You will need</strong><ul>${t.requirements.map(r=>`<li>${e(r.title)} · ${r.quantity} ${e(r.unit)}</li>`).join('')}</ul><p>Tools and supplies: ${e(t.already_have)}</p><strong>A simple build plan</strong><ol>${t.steps.map(s=>`<li>${e(s)}</li>`).join('')}</ol><p>Check dimensions and material suitability before building. Ask for help with cutting and tools when needed.</p></div><div class="form-actions">${button('Use this project plan','use-template',t.id,'primary','arrow')}</div>`);},
  'use-template':el=>showProjectForm(el.dataset.id),
  match:el=>showMatch(el.dataset.id),
  contribute:showContribution,
  'add-requirement':()=>{if ($$('.req-form').length>=20) return toast('A project can have up to 20 requirements.',true);$('#requirements').insertAdjacentHTML('beforeend',requirementForm({},$$('.req-form').length+1));},
  'remove-requirement':el=>{if ($$('.req-form').length<=1) return toast('Keep at least one material requirement.',true);el.closest('.req-form').remove();},
  'add-contribution':()=>{if ($$('.contribution-form-item').length>=10) return toast('Add up to 10 items per contribution.',true);$('#contribution-items').insertAdjacentHTML('beforeend',contributionFormItem({},$$('.contribution-form-item').length+1));},
  'remove-contribution':el=>el.closest('.contribution-form-item').remove(),
  'demo-login':async el=>{await api('demo-login',{user_id:el.dataset.id});await refresh();closeModal();toast('Switched account. You’re ready to explore.');},
  logout:async()=>{await api('logout',{});await refresh();navigate('market');toast('You’re signed out.');},
  profile:()=>{const u=state.data.user;openModal(e(u.name),'Your community account',`<div class="detail-stats"><div class="detail-stat"><small>ROLE</small><strong>${e(pretty(u.role))}</strong></div><div class="detail-stat"><small>JOINED</small><strong>${formatDate(u.created_at)}</strong></div><div class="detail-stat"><small>COMMUNITY</small><strong>${e(u.community)}</strong></div></div><p class="text-block">${e(u.email)}</p><div class="form-actions">${state.data.demo?button('Switch demo account','demo','','secondary'):''}${button('Sign out','logout','','primary','logout')}</div>`);},
  notifications:async()=>{if(!requireAuth())return;const list=state.data.notifications;openModal('A little news from the loop.','Requests, approvals, and the next steps for your exchanges.',list.map(n=>`<article class="notification${!n.read_at?' unread':''}">${e(n.body)}<small>${fullDate(n.created_at)}</small></article>`).join('')||'<p class="text-block">You’re all caught up. New updates will appear here.</p>');await api('notifications',{});await refresh();},
  'archive-item':el=>confirmAction('Archive this listing?','The item will be removed from discovery. An item with an active reservation cannot be archived.','Archive listing',async()=>{await api(`items/${el.dataset.id}/archive`,{});await refresh();closeModal();toast('Listing archived.');}),
  wanted:async el=>{await api(`projects/${el.dataset.id}/wanted`,{});await refresh();toast('Your missing materials are on the community noticeboard.');},
  'request-kit':async()=>{const m=state.match;const k=await api('kits',{project_id:m.project.id,fingerprint:m.fingerprint});await refresh();showKit(k.id);toast('Kit requested. Owners have been notified.');},
  kit:el=>showKit(el.dataset.id), exchange:el=>showExchange(el.dataset.id),
  'approve-kit':async el=>{await api(`kits/${el.dataset.id}/approve`,{});await refresh();showKit(el.dataset.id);toast('Your materials are approved. Please arrange delivery to the desk.');},
  'receive-line':el=>{const k=$('.modal').dataset.kitId;confirmAction('Have the materials arrived?','Confirm that you have physically received this quantity and checked that its condition and specifications match the request.','Confirm checked in',async()=>{await api(`kits/${k}/receive`,{line_id:el.dataset.id});await refresh();showKit(k);toast('Material checked in.');});},
  'return-line':el=>{const k=$('.modal').dataset.kitId;confirmAction('Return materials to their owner.','Confirm that these materials have been physically returned. They will become available on the owner’s listing again.','Confirm returned',async()=>{await api(`kits/${k}/return`,{line_id:el.dataset.id});await refresh();showKit(k);toast('Return recorded.');});},
  'collect-kit':el=>confirmAction('Is the whole kit in your hands?','Check every quantity and condition before confirming. Your held credits will now be transferred to the suppliers, and shared-stock credits will be retired.','I have collected the complete kit',async()=>{await api(`kits/${el.dataset.id}/collect`,{});await refresh();showKit(el.dataset.id);toast('Kit collected. Every supplier has received their share.');}),
  'cancel-kit':el=>confirmAction('Cancel this project kit?','Your credit hold will be released. Materials already delivered to the desk must be returned before they become available again. An earlier contribution to shared stock is a separate transaction.','Cancel kit',async()=>{await api(`kits/${el.dataset.id}/cancel`,{});await refresh();showKit(el.dataset.id);toast('Kit cancelled and credits released.');}),
  'accept-exchange':async el=>{await api(`exchanges/${el.dataset.id}/accept`,{});await refresh();showExchange(el.dataset.id);toast('Request accepted. Arrange your handover.');},
  'confirm-exchange':el=>confirmAction('Confirm the handover.','Only confirm after the actual goods have changed hands and you have checked the agreed condition. Settlement happens once both participants confirm.','Confirm my handover',async()=>{await api(`exchanges/${el.dataset.id}/confirm`,{});await refresh();showExchange(el.dataset.id);toast('Your handover confirmation is recorded.');}),
  'cancel-exchange':el=>confirmAction('Cancel this exchange?','The reserved items and credits will become available again. Exchanges cannot be cancelled once either participant has confirmed a handover.','Cancel exchange',async()=>{await api(`exchanges/${el.dataset.id}/cancel`,{});await refresh();showExchange(el.dataset.id);toast('Exchange cancelled.');}),
  'complete-project':el=>showComplete(el.dataset.id), 'project-detail':el=>showProjectDetail(el.dataset.id), 'relist-project':el=>showRelist(el.dataset.id),
  'review-contribution':el=>showReview(el.dataset.id),
  'reverse-contribution':el=>confirmAction('Return the original contribution?','All original goods must still be present and unreserved. The contributor must have enough available credits to reverse the full original value. Record this only when returning the goods.','Return goods and retire credits',async()=>{await api(`contributions/${el.dataset.id}/reverse`,{});await refresh();closeModal();toast('Contribution reversed. Matching credits were retired.');}),
  'toggle-issuance':async()=>{const paused=!state.data.reconciliation.paused;await api('issuance',{paused});await refresh();toast(paused?'New credit issuance is paused.':'New credit issuance is enabled.');},
  'chat-kit':el=>showChat('kit',el.dataset.id), 'chat-exchange':el=>showChat('exchange',el.dataset.id),
};
document.addEventListener('click',async event=>{
  if(event.target.classList.contains('backdrop')) return closeModal();
  if($('.sidebar.open')&&!event.target.closest('.sidebar')&&!event.target.closest('.mobile-menu')) $('.sidebar').classList.remove('open');
  const el=event.target.closest('[data-action]'); if(!el || el.disabled)return;
  const action=actions[el.dataset.action]; if(!action)return;
  event.preventDefault();
  el.disabled=true;
  try { await action(el); } catch(error){toast(error.message,true);} finally {el.disabled=false;}
});
document.addEventListener('input',event=>{if(event.target.id==='market-search'){state.filter.q=event.target.value;updateMarket();}});
document.addEventListener('change',event=>{if(event.target.id==='mode-filter'){state.filter.mode=event.target.value;updateMarket();}});
document.addEventListener('submit',async event=>{
  const form=event.target.closest('form[data-form]');if(!form)return;event.preventDefault();
  const btn=$('button[type=submit]',form), err=$('.form-error',form);if(err)err.textContent='';if(btn)btn.disabled=true;
  try{
    const type=form.dataset.form, data=values(form), ref=form.dataset.id;
    if(type==='login'||type==='register'){await api(type,data);await refresh();closeModal();toast(type==='register'?'Welcome to your community.':'Welcome back.');}
    else if(type==='item'){await api(ref?`items/${ref}/edit`:'items',await readItemFields($('.item-fields',form)));await refresh();closeModal();toast(ref?'Listing updated.':'Your item is in the marketplace.');}
    else if(type==='contribution'){const items=[];for(const fields of $$('.item-fields',form))items.push(await readItemFields(fields));await api('contributions',{items});await refresh();navigate('wallet');toast('Contribution submitted. Arrange your drop-off with the organiser.');}
    else if(type==='project'){const payload={name:data.name,description:data.description,already_have:data.already_have,needed_by:data.needed_by,requirements:$$('.req-form',form).map(values)};const project=await api(ref?`projects/${ref}/edit`:'projects',payload);await refresh();await showMatch(project.id);}
    else if(type==='exchange-request'){const exchange=await api('exchanges',{...data,item_id:ref});await refresh();showExchange(exchange.id);toast('Request sent.');}
    else if(type==='review'){await api(`contributions/${ref}/review`,data);await refresh();closeModal();toast(data.decision==='accept'?'Goods accepted. Credits issued.':'Review saved.');}
    else if(type==='confirmation'){const callback=state.confirmation;if(callback)await callback();}
    else if(type==='complete-project'){const used={};for(const [key,value]of Object.entries(data))if(key.startsWith('used_'))used[key.slice(5)]=value;await api(`projects/${ref}/complete`,{note:data.note,image:await photoFrom($('input[type=file]',form)),shared:data.shared,used});await refresh();showProjectDetail(ref);toast('Another good idea brought to life.');}
    else if(type==='relist'){await api(`projects/${ref}/relist`,await readItemFields($('.item-fields',form)));await refresh();navigate('listings');toast('Your creation is in the marketplace.');}
    else if(type==='chat'){await api('messages',{kind:form.dataset.kind,reference:ref,body:data.body});$('textarea',form).value='';await refreshChat();}
  }catch(error){if(err?.isConnected)err.textContent=error.message;else toast(error.message,true);}finally{if(btn)btn.disabled=false;}
});
document.addEventListener('keydown',event=>{
  if(!$('.modal'))return;
  if(event.key==='Escape'){closeModal();return;}
  if(event.key==='Tab'){
    const focusable=$$('button:not([disabled]),input,select,textarea,a[href]', $('.modal')).filter(el=>el.offsetParent!==null);
    if(!focusable.length)return;
    const first=focusable[0],last=focusable.at(-1);
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
  }
});
window.addEventListener('hashchange',()=>{const route=location.hash.slice(2)||'market';if(state.route!==route){state.route=route;render();closeModal();}});
state.route=location.hash.slice(2)||'market';
refresh().catch(error=>{$('#app').innerHTML=empty('We couldn’t open your community.',e(error.message)+' Check that the EcoLoop server is running, then refresh the page.');});
setInterval(()=>{if(state.chat)refreshChat().catch(()=>{});},5000);
