// --- UI Helpers ---
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const icon = type === 'success' ? 'fa-check' : 'fa-circle-exclamation';

    toast.className = 'toast-enter pointer-events-auto flex items-center gap-3 pl-4 pr-5 py-3 text-sm text-neutral-100 bg-neutral-900 border border-neutral-700 rounded-xl shadow-2xl max-w-xs';
    toast.innerHTML = `
        <span class="w-6 h-6 rounded-full ${type === 'success' ? 'bg-white text-neutral-950' : 'bg-neutral-700 text-white'} flex items-center justify-center shrink-0">
            <i class="fa-solid ${icon} text-[11px]"></i>
        </span>
        <span class="font-medium">${escapeHtml(message)}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.replace('toast-enter', 'toast-exit');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, s => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[s]));
}

// Reputation badge shared between marketplace and listings
function reviewBadge(pos, neg, opts = {}) {
    const p = pos || 0;
    const n = neg || 0;
    const size = opts.small ? 'text-[10px]' : 'text-[11px]';
    return `
        <span class="inline-flex items-center gap-1.5 ${size} text-neutral-400">
            <span class="inline-flex items-center gap-1 text-neutral-200"><i class="fa-solid fa-thumbs-up text-[9px]"></i>${p}</span>
            <span class="text-neutral-700">·</span>
            <span class="inline-flex items-center gap-1 text-neutral-500"><i class="fa-solid fa-thumbs-down text-[9px]"></i>${n}</span>
        </span>
    `;
}

// --- Global State & Polling ---
let allItems = [];
let myItems = [];
let allTrades = [];
let currentTab = 'market';
let globalPollInterval = null;
let allChampions = [];

// Real-time: poll every second and re-render only when data changes
function startPolling() {
    if (globalPollInterval) clearInterval(globalPollInterval);
    globalPollInterval = setInterval(() => {
        loadProfile(true);
        loadMarketplace(true);
        loadMyItems(true);
        loadTrades(true);
        loadChampion(true);
    }, 1000);
}

// --- Tab switching ---
function setActiveTab(btn) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('bg-neutral-900', 'text-white');
        b.classList.add('text-neutral-400');
    });
    btn.classList.remove('text-neutral-400');
    btn.classList.add('bg-neutral-900', 'text-white');
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        setActiveTab(btn);
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

        currentTab = btn.getAttribute('data-tab');
        document.getElementById('tab-' + currentTab).classList.remove('hidden');

        if (currentTab === 'market') loadMarketplace();
        if (currentTab === 'my-items') loadMyItems();
        if (currentTab === 'trades') loadTrades();
        if (currentTab === 'champion') loadChampion();
        if (currentTab === 'profile') loadProfile();
    });
});

document.getElementById('logout-btn').addEventListener('click', async () => {
    await fetch('api/auth.php?action=logout', { method: 'POST' });
    window.location.href = 'login.php';
});

async function init() {
    // activate the default (market) tab styling
    const marketBtn = document.querySelector('.tab-btn[data-tab="market"]');
    if (marketBtn) setActiveTab(marketBtn);

    await loadProfile();
    await loadMarketplace();
    startPolling();
}

async function loadProfile(silent = false) {
    const res = await fetch('api/users.php?action=profile');
    const data = await res.json();
    if (data.success) {
        document.getElementById('header-pos').innerHTML = `${data.profile.positive_reviews} <i class="fa-solid fa-thumbs-up text-[9px]"></i>`;
        document.getElementById('header-neg').innerHTML = `${data.profile.negative_reviews} <i class="fa-solid fa-thumbs-down text-[9px]"></i>`;

        if (document.getElementById('prof-pos')) {
            document.getElementById('prof-trades').textContent = data.profile.completed_trades;
            document.getElementById('prof-pos').innerHTML = `${data.profile.positive_reviews} <i class="fa-solid fa-thumbs-up text-sm text-neutral-500"></i>`;
            document.getElementById('prof-neg').textContent = data.profile.negative_reviews;
        }
    }
}

async function loadMarketplace(silent = false) {
    const res = await fetch('api/items.php?action=list');
    const data = await res.json();
    if (data.success) {
        if (silent && JSON.stringify(allItems) === JSON.stringify(data.items)) return;
        allItems = data.items;
        renderMarketplace();
    }
}

document.getElementById('search-tags').addEventListener('input', renderMarketplace);

function renderMarketplace() {
    const grid = document.getElementById('market-grid');
    const query = document.getElementById('search-tags').value.toLowerCase();

    const items = allItems.filter(i => i.owner_id !== CURRENT_USER_ID && !i.is_locked);
    let html = '';
    let count = 0;

    items.forEach(item => {
        if (query && !item.tags.some(t => t.toLowerCase().includes(query)) && !item.title.toLowerCase().includes(query)) return;
        count++;

        let lookingForHtml = '';
        if (item.looking_for_tags && item.looking_for_tags.length > 0) {
            lookingForHtml = `<div class="mb-3 text-xs text-neutral-500">
                <i class="fa-solid fa-bullseye text-[10px] mr-1 text-neutral-400"></i> Wants:
                <span class="text-neutral-300">${escapeHtml(item.looking_for_tags.join(', '))}</span>
            </div>`;
        }

        html += `
            <div class="group bg-neutral-900/60 border border-neutral-800 rounded-2xl overflow-hidden hover:border-neutral-700 transition-colors flex flex-col">
                <div class="h-44 bg-neutral-950 relative overflow-hidden">
                    ${item.image ? `<img src="${escapeHtml(item.image)}" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300">` : `<div class="flex items-center justify-center h-full text-neutral-700"><i class="fa-solid fa-image text-3xl"></i></div>`}
                </div>
                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="font-semibold text-[15px] text-white leading-tight">${escapeHtml(item.title)}</h3>
                    </div>
                    <p class="text-sm text-neutral-500 mb-3 line-clamp-2 flex-1">${escapeHtml(item.description)}</p>
                    ${lookingForHtml}
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        ${item.tags.map(t => `<span class="bg-neutral-800 text-[10px] px-2 py-0.5 rounded-md text-neutral-400">${escapeHtml(t)}</span>`).join('')}
                    </div>
                    <div class="flex items-center justify-between border-t border-neutral-800 pt-3 mb-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-6 h-6 rounded-full bg-neutral-800 border border-neutral-700 flex items-center justify-center text-[10px] font-semibold shrink-0">${escapeHtml((item.owner_username || '?').charAt(0).toUpperCase())}</div>
                            <span class="text-xs text-neutral-300 font-medium truncate">${escapeHtml(item.owner_username)}</span>
                        </div>
                        ${reviewBadge(item.owner_pos, item.owner_neg)}
                    </div>
                    <button onclick="proposeTrade('${item.id}')" class="w-full bg-neutral-800 text-neutral-100 hover:bg-white hover:text-neutral-950 py-2 rounded-lg text-sm font-medium transition-colors">
                        Request trade
                    </button>
                </div>
            </div>
        `;
    });

    if (count === 0) {
        html = '<div class="col-span-full text-center py-16 text-neutral-600 text-sm">No available items match your criteria.</div>';
    }
    grid.innerHTML = html;
}

async function loadMyItems(silent = false) {
    const res = await fetch('api/items.php?action=list');
    const data = await res.json();
    if (data.success) {
        if (silent && JSON.stringify(allItems) === JSON.stringify(data.items)) return;
        allItems = data.items;
        myItems = data.items.filter(i => i.owner_id === CURRENT_USER_ID);
        const grid = document.getElementById('my-items-grid');

        let html = '';
        if (myItems.length === 0) {
            grid.innerHTML = '<div class="col-span-full text-center py-16 text-neutral-600 text-sm">You haven\'t listed any items yet.</div>';
            return;
        }

        myItems.forEach(item => {
            html += `
                <div class="bg-neutral-900/60 border border-neutral-800 rounded-2xl overflow-hidden group relative">
                    <div class="absolute inset-0 bg-black/70 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col gap-2 items-center justify-center z-10 backdrop-blur-sm">
                        <button onclick="openEditItemModal('${item.id}')" class="bg-white hover:bg-neutral-200 text-neutral-950 px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fa-solid fa-pen mr-1.5 text-xs"></i> Edit
                        </button>
                        ${item.is_locked ? `<span class="text-[11px] text-neutral-300 bg-neutral-800 px-2.5 py-1 rounded-md border border-neutral-700"><i class="fa-solid fa-lock text-[9px] mr-1"></i> In active trade</span>` : ''}
                    </div>
                    <div class="h-36 bg-neutral-950 relative">
                        ${item.image ? `<img src="${escapeHtml(item.image)}" class="w-full h-full object-cover">` : `<div class="flex items-center justify-center h-full text-neutral-700"><i class="fa-solid fa-image text-2xl"></i></div>`}
                        ${item.is_locked ? `<div class="absolute top-2.5 left-2.5 bg-neutral-900/90 text-neutral-300 text-[10px] px-2 py-1 rounded-md border border-neutral-700"><i class="fa-solid fa-lock text-[9px] mr-1"></i> Locked</div>` : ''}
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-white mb-1.5">${escapeHtml(item.title)}</h3>
                        ${item.looking_for_tags?.length ? `<p class="text-[11px] text-neutral-500 mb-2"><i class="fa-solid fa-bullseye text-[9px] mr-1"></i> ${escapeHtml(item.looking_for_tags.join(', '))}</p>` : ''}
                        <div class="flex flex-wrap gap-1.5">
                            ${item.tags.map(t => `<span class="bg-neutral-800 text-[10px] px-2 py-0.5 rounded-md text-neutral-400">${escapeHtml(t)}</span>`).join('')}
                        </div>
                    </div>
                </div>
            `;
        });
        grid.innerHTML = html;
    }
}

function openItemModal() {
    document.getElementById('add-item-form').reset();
    document.getElementById('edit-item-id').value = '';
    document.getElementById('item-modal-title').textContent = 'List an Item';
    document.getElementById('add-item-btn-text').textContent = 'Post Item';
    document.getElementById('img-edit-hint').classList.add('hidden');
    document.getElementById('delete-item-btn').classList.add('hidden');
    openModal('add-item-modal');
}

function openEditItemModal(itemId) {
    const item = myItems.find(i => i.id === itemId) || allItems.find(i => i.id === itemId);
    if (!item) return;
    document.getElementById('edit-item-id').value = item.id;
    document.getElementById('item-title').value = item.title;
    document.getElementById('item-desc').value = item.description;
    document.getElementById('item-tags').value = (item.tags || []).join(', ');
    document.getElementById('item-looking-for').value = (item.looking_for_tags || []).join(', ');

    document.getElementById('item-modal-title').textContent = 'Edit Item';
    document.getElementById('add-item-btn-text').textContent = 'Save Changes';
    document.getElementById('img-edit-hint').classList.remove('hidden');
    document.getElementById('delete-item-btn').classList.remove('hidden');
    openModal('add-item-modal');
}

document.getElementById('delete-item-btn').addEventListener('click', async () => {
    const editId = document.getElementById('edit-item-id').value;
    if (!editId) return;

    const res = await fetch(`api/items.php?action=delete`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ item_id: editId })
    });
    const data = await res.json();
    if(data.success) {
        closeModal('add-item-modal');
        showToast("Item deleted");
        loadMyItems();
        loadMarketplace();
    } else {
        showToast(data.error, 'error');
    }
});

document.getElementById('add-item-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btnText = document.getElementById('add-item-btn-text');
    const spinner = document.getElementById('add-item-spinner');
    const originalText = btnText.textContent;
    btnText.textContent = 'Saving...';
    spinner.classList.remove('hidden');

    const editId = document.getElementById('edit-item-id').value;
    const action = editId ? 'edit' : 'create';

    const formData = new FormData();
    if(editId) formData.append('item_id', editId);
    formData.append('title', document.getElementById('item-title').value);
    formData.append('description', document.getElementById('item-desc').value);
    formData.append('tags', document.getElementById('item-tags').value);
    formData.append('looking_for_tags', document.getElementById('item-looking-for').value);

    const fileField = document.getElementById('item-image');
    if (fileField.files[0]) formData.append('image', fileField.files[0]);

    try {
        const res = await fetch(`api/items.php?action=${action}`, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            closeModal('add-item-modal');
            showToast(editId ? 'Item updated' : 'Item posted');
            loadMyItems();
            loadMarketplace();
        } else {
            showToast(data.error, 'error');
        }
    } catch(err) {
        showToast("Error saving item", 'error');
    } finally {
        btnText.textContent = originalText;
        spinner.classList.add('hidden');
    }
});

function proposeTrade(wantedId) {
    const wanted = allItems.find(i => i.id === wantedId);
    if (!wanted) return;
    const wantedLookingFor = (wanted.looking_for_tags || []).join(', ');

    document.getElementById('wanted-item-id').value = wantedId;
    document.getElementById('wanted-item-title').textContent = wanted.title;
    document.getElementById('wanted-looking-for').innerHTML = wantedLookingFor ? `<i class="fa-solid fa-bullseye text-[10px] mr-1"></i> Looking for: <span class="text-neutral-300 font-medium">${escapeHtml(wantedLookingFor)}</span>` : 'Open to any offers';

    const list = document.getElementById('offer-items-list');
    list.innerHTML = '';

    const availableItems = myItems.filter(i => !i.is_locked);

    if (availableItems.length === 0) {
        list.innerHTML = '<p class="text-sm text-neutral-400 bg-neutral-800/50 p-3 rounded-lg border border-neutral-700">You have no unlocked items to offer. List a new item first.</p>';
        document.getElementById('submit-trade-btn').disabled = true;
    } else {
        availableItems.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex items-center p-3 border border-neutral-800 rounded-lg hover:border-neutral-600 hover:bg-neutral-800/50 cursor-pointer transition-colors group';
            div.innerHTML = `
                <input type="radio" name="offer_item" value="${item.id}" class="mr-3 accent-white w-4 h-4">
                <span class="text-neutral-200 text-sm font-medium group-hover:text-white transition-colors">${escapeHtml(item.title)}</span>
            `;
            div.addEventListener('click', () => {
                div.querySelector('input').checked = true;
                document.getElementById('submit-trade-btn').disabled = false;
            });
            list.appendChild(div);
        });
        document.getElementById('submit-trade-btn').disabled = true;
    }

    openModal('propose-trade-modal');
}

document.getElementById('submit-trade-btn').addEventListener('click', async () => {
    const wantedId = document.getElementById('wanted-item-id').value;
    const selectedOffer = document.querySelector('input[name="offer_item"]:checked');
    if (!selectedOffer) return;
    const offerId = selectedOffer.value;

    document.getElementById('submit-trade-btn').disabled = true;
    document.getElementById('submit-trade-btn').textContent = "Sending...";

    const res = await fetch('api/trades.php?action=propose', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ offered_item_id: offerId, wanted_item_id: wantedId })
    });
    const data = await res.json();
    if(data.success) {
        closeModal('propose-trade-modal');
        showToast("Trade request sent");
        loadTrades();
        loadMarketplace();
        loadMyItems();
    } else {
        showToast(data.error, 'error');
    }
    document.getElementById('submit-trade-btn').textContent = "Send request";
});

async function loadTrades(silent = false) {
    const res = await fetch('api/trades.php?action=list');
    const data = await res.json();
    if(data.success) {
        data.trades.sort((a,b) => b.created_at - a.created_at);
        if(silent && JSON.stringify(allTrades) === JSON.stringify(data.trades)) return;
        allTrades = data.trades;

        const list = document.getElementById('trades-list');
        const activeTrades = allTrades.filter(t => t.status !== 'cancelled');

        if (activeTrades.length === 0) {
            list.innerHTML = '<div class="text-center py-16 text-neutral-600 text-sm">No active trades.</div>';
            return;
        }

        let html = '';
        activeTrades.forEach(trade => {
            const isProposer = trade.proposer_id === CURRENT_USER_ID;
            const otherUser = isProposer ? trade.receiver_username : trade.proposer_username;
            const myItem = isProposer ? trade.offered_item_title : trade.wanted_item_title;
            const theirItem = isProposer ? trade.wanted_item_title : trade.offered_item_title;

            let statusBadge = '';
            if (trade.status === 'pending') {
                statusBadge = '<span class="bg-neutral-800 text-neutral-400 px-2.5 py-1 rounded-md text-[11px] font-medium border border-neutral-700"><i class="fa-solid fa-clock text-[9px] mr-1"></i> Pending</span>';
            }
            if (trade.status === 'accepted') {
                statusBadge = '<span class="bg-neutral-800 text-neutral-100 px-2.5 py-1 rounded-md text-[11px] font-medium border border-neutral-600"><i class="fa-solid fa-handshake text-[9px] mr-1"></i> Meeting setup</span>';
            }
            if (trade.status === 'completed') {
                statusBadge = '<span class="bg-white text-neutral-950 px-2.5 py-1 rounded-md text-[11px] font-semibold"><i class="fa-solid fa-check text-[9px] mr-1"></i> Completed</span>';
            }

            html += `
                <div class="bg-neutral-900/60 border border-neutral-800 rounded-2xl p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:border-neutral-700 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2.5 mb-2.5">
                            <div class="w-7 h-7 rounded-full bg-neutral-800 border border-neutral-700 flex items-center justify-center text-[11px] font-semibold">${escapeHtml((otherUser || '?').charAt(0).toUpperCase())}</div>
                            <span class="text-white font-medium text-sm">${escapeHtml(otherUser)}</span>
                            ${statusBadge}
                        </div>
                        <div class="text-xs text-neutral-400 flex flex-col sm:flex-row sm:items-center gap-1.5">
                            <span class="bg-neutral-950 border border-neutral-800 px-2.5 py-1.5 rounded-lg"><i class="fa-solid fa-arrow-down text-neutral-500 mr-1.5 text-[9px]"></i> Get <span class="text-white font-medium">${escapeHtml(theirItem)}</span></span>
                            <span class="bg-neutral-950 border border-neutral-800 px-2.5 py-1.5 rounded-lg"><i class="fa-solid fa-arrow-up text-neutral-500 mr-1.5 text-[9px]"></i> Give <span class="text-white font-medium">${escapeHtml(myItem)}</span></span>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2 w-full sm:w-auto">
                        <button onclick="openChat('${trade.id}')" class="flex-1 sm:flex-none bg-neutral-800 hover:bg-neutral-700 text-neutral-100 px-5 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
                            <i class="fa-solid fa-comment text-xs"></i> ${trade.status === 'completed' ? 'History' : 'Open chat'}
                        </button>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;

        if (currentChatTrade) {
            const updatedTrade = allTrades.find(t => t.id === currentChatTrade.id);
            if (updatedTrade && updatedTrade.status !== currentChatTrade.status) {
                currentChatTrade = updatedTrade;
                renderChatHeader(updatedTrade);
            }
        }
    }
}

let currentChatTrade = null;
let chatMessagesCache = [];
let chatPollInterval = null;

function openChat(tradeId) {
    const trade = allTrades.find(t => t.id === tradeId);
    if (!trade) return;
    currentChatTrade = trade;
    document.getElementById('trade-chat-modal').classList.remove('hidden');
    setTimeout(() => { document.getElementById('chat-panel').classList.remove('translate-x-full'); }, 10);
    document.getElementById('chat-trade-id').value = trade.id;

    renderChatHeader(trade);

    chatMessagesCache = [];
    pollMessages();
    if(chatPollInterval) clearInterval(chatPollInterval);
    chatPollInterval = setInterval(pollMessages, 1000); // real-time chat
}

function renderChatHeader(trade) {
    const isProposer = trade.proposer_id === CURRENT_USER_ID;
    const otherUser = isProposer ? trade.receiver_username : trade.proposer_username;
    document.getElementById('chat-trade-details').innerHTML = `Trading with <strong class="text-white">${escapeHtml(otherUser)}</strong>`;

    const actions = document.getElementById('chat-actions');
    actions.innerHTML = '';
    document.getElementById('chat-form').classList.remove('hidden');

    if (trade.status === 'pending') {
        document.getElementById('chat-status-text').textContent = 'Pending';
        if (!isProposer) {
            actions.innerHTML = `
                <div class="flex gap-2">
                    <button onclick="cancelTrade('${trade.id}')" class="bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium px-3 py-2 rounded-lg transition-colors"><i class="fa-solid fa-xmark mr-1"></i> Decline</button>
                    <button onclick="acceptTrade('${trade.id}')" class="bg-white hover:bg-neutral-200 text-neutral-950 text-xs font-semibold px-4 py-2 rounded-lg transition-colors"><i class="fa-solid fa-check mr-1"></i> Accept</button>
                </div>
            `;
        } else {
            actions.innerHTML = `
                <div class="flex gap-2 items-center">
                    <button onclick="cancelTrade('${trade.id}')" class="text-neutral-500 hover:text-neutral-300 text-xs px-2 transition-colors"><i class="fa-solid fa-xmark mr-1"></i> Cancel</button>
                    <span class="text-xs text-neutral-400 font-medium">Waiting…</span>
                </div>
            `;
        }
    } else if (trade.status === 'accepted') {
        document.getElementById('chat-status-text').textContent = 'Meeting setup';
        const myCompleted = isProposer ? trade.proposer_completed : trade.receiver_completed;

        if (!myCompleted) {
            actions.innerHTML = `<button onclick="openRatingModal('${trade.id}')" class="bg-white hover:bg-neutral-200 text-neutral-950 text-xs font-semibold px-4 py-2 rounded-lg transition-colors"><i class="fa-solid fa-check mr-1"></i> Complete trade</button>`;
        } else {
            actions.innerHTML = `<span class="text-xs text-neutral-300 bg-neutral-800 px-2.5 py-1.5 rounded-lg border border-neutral-700"><i class="fa-solid fa-check-double mr-1"></i> Waiting for ${escapeHtml(otherUser)}</span>`;
        }
    } else {
        document.getElementById('chat-status-text').textContent = 'Completed';
        actions.innerHTML = `<span class="text-xs text-neutral-950 font-semibold bg-white px-3 py-1.5 rounded-full"><i class="fa-solid fa-lock mr-1"></i> Finished</span>`;
        document.getElementById('chat-form').classList.add('hidden');
    }
}

function closeChat() {
    document.getElementById('chat-panel').classList.add('translate-x-full');
    setTimeout(() => { document.getElementById('trade-chat-modal').classList.add('hidden'); }, 300);
    currentChatTrade = null;
    document.getElementById('chat-form').classList.remove('hidden');
    if(chatPollInterval) clearInterval(chatPollInterval);
}

async function pollMessages() {
    if (!currentChatTrade) return;
    const res = await fetch(`api/messages.php?action=list&trade_id=${currentChatTrade.id}`);
    const data = await res.json();
    if(data.success) {
        if (JSON.stringify(data.messages) === JSON.stringify(chatMessagesCache)) return;
        chatMessagesCache = data.messages;

        const container = document.getElementById('chat-messages');
        const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 50;

        let html = '';
        if (data.messages.length === 0) {
            html = '<p class="text-center text-xs text-neutral-600 mt-10"><i class="fa-solid fa-comment-dots text-2xl mb-2 block"></i> Start discussing details.</p>';
        } else {
            data.messages.forEach(m => {
                const isMe = m.sender_id === CURRENT_USER_ID;
                html += `
                    <div class="flex ${isMe ? 'justify-end' : 'justify-start'}">
                        <div class="${isMe ? 'bg-white text-neutral-950 rounded-2xl rounded-br-sm' : 'bg-neutral-800 text-neutral-100 rounded-2xl rounded-bl-sm'} px-3.5 py-2.5 max-w-[80%] shadow-sm">
                            ${!isMe ? `<div class="text-[10px] text-neutral-400 font-semibold mb-0.5">${escapeHtml(m.sender_username)}</div>` : ''}
                            <div class="text-sm leading-snug">${escapeHtml(m.text)}</div>
                        </div>
                    </div>
                `;
            });
        }
        container.innerHTML = html;
        if(isScrolledToBottom) container.scrollTop = container.scrollHeight;
    }
}

document.getElementById('chat-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const text = input.value;
    const tradeId = document.getElementById('chat-trade-id').value;
    input.value = '';

    await fetch('api/messages.php?action=send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ trade_id: tradeId, text: text })
    });
    pollMessages();
});

async function acceptTrade(tradeId) {
    await fetch('api/trades.php?action=accept', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ trade_id: tradeId })
    });
    showToast('Trade accepted');
    loadTrades();
}

async function cancelTrade(tradeId) {
    await fetch('api/trades.php?action=cancel', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ trade_id: tradeId })
    });
    showToast('Trade cancelled', 'error');
    closeChat();
    loadTrades();
}

function openRatingModal(tradeId) {
    document.getElementById('rating-trade-id').value = tradeId;
    openModal('rating-modal');
}

async function submitRating(rating) {
    const tradeId = document.getElementById('rating-trade-id').value;
    closeModal('rating-modal');
    closeChat();

    await fetch('api/trades.php?action=complete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ trade_id: tradeId, rating: rating })
    });

    showToast("Trade completed & rated");
    loadTrades();
    loadProfile();
}

async function loadChampion(silent = false) {
    const res = await fetch('api/users.php?action=leaderboard');
    const data = await res.json();
    if (data.success) {
        if(silent && JSON.stringify(allChampions) === JSON.stringify(data.topUsers)) return;
        allChampions = data.topUsers;

        const list = document.getElementById('champion-list');

        if (!data.topUsers || data.topUsers.length === 0) {
            list.innerHTML = `<p class="text-center text-neutral-600 py-10 text-sm">No trading activity in your area yet. Be the first!</p>`;
            return;
        }

        let html = '';
        data.topUsers.forEach((user, index) => {
            const pos = user.positive_reviews || 0;
            const neg = user.negative_reviews || 0;

            let rank = `<span class="text-sm font-bold text-neutral-600 w-7 text-center">${index + 1}</span>`;
            if (index === 0) rank = `<span class="w-7 h-7 rounded-full bg-white text-neutral-950 flex items-center justify-center text-xs font-bold"><i class="fa-solid fa-crown"></i></span>`;
            else if (index === 1) rank = `<span class="w-7 h-7 rounded-full bg-neutral-700 text-white flex items-center justify-center text-xs font-bold">2</span>`;
            else if (index === 2) rank = `<span class="w-7 h-7 rounded-full bg-neutral-800 text-neutral-300 flex items-center justify-center text-xs font-bold">3</span>`;

            const topStyle = index === 0 ? 'border-neutral-600 bg-neutral-900' : 'border-neutral-800 bg-neutral-900/60';

            html += `
                <div class="flex items-center justify-between p-4 rounded-xl border ${topStyle} hover:border-neutral-700 transition-colors">
                    <div class="flex items-center gap-3.5">
                        ${rank}
                        <div class="w-8 h-8 rounded-full bg-neutral-800 border border-neutral-700 flex items-center justify-center text-xs font-semibold">${escapeHtml((user.username || '?').charAt(0).toUpperCase())}</div>
                        <div>
                            <div class="font-medium text-white text-sm">${escapeHtml(user.username)}</div>
                            <div class="text-[11px] text-neutral-500">Pincode ${escapeHtml(user.pincode)}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1 text-neutral-200"><i class="fa-solid fa-thumbs-up text-[10px]"></i> ${pos}</span>
                        <span class="inline-flex items-center gap-1 text-neutral-500"><i class="fa-solid fa-thumbs-down text-[10px]"></i> ${neg}</span>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;
    }
}

// Kick everything off
init();
