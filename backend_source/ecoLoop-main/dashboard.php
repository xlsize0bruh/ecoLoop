<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace — EcoLoop</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Subtle scrollbar for main areas */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #262626; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #404040; }

        @keyframes slideInRight {
            from { transform: translateX(110%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; transform: translateX(20px); } }
        .toast-enter { animation: slideInRight 0.25s cubic-bezier(0.16,1,0.3,1) forwards; }
        .toast-exit { animation: fadeOut 0.25s ease-out forwards; }

        @keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
        .live-dot { animation: pulse-dot 1.6s ease-in-out infinite; }
    </style>
</head>
<body class="bg-neutral-950 text-neutral-100 font-sans antialiased h-screen overflow-hidden flex flex-col selection:bg-white selection:text-neutral-950">

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>

    <!-- Navbar -->
    <header class="border-b border-neutral-900 bg-neutral-950 z-20 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 w-full">
            <div class="flex justify-between items-center h-16">
                <a href="dashboard.php" class="flex items-center gap-2.5" aria-label="EcoLoop marketplace">
                    <img src="assets/favicon.svg" alt="" class="w-8 h-8 rounded-[9px]">
                    <span class="font-semibold text-[15px] tracking-tight hidden sm:block">EcoLoop</span>
                </a>

                <nav class="absolute left-1/2 -translate-x-1/2 flex items-center rounded-xl border border-neutral-800 bg-neutral-900 p-1" aria-label="EcoLoop sections">
                    <a href="dashboard.php" aria-current="page" class="bg-neutral-700 text-white px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-sm font-medium">Marketplace</a>
                    <a href="projects.php" class="text-neutral-400 hover:text-white px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-sm font-medium">Projects</a>
                </nav>

                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex items-center gap-1.5 text-xs text-neutral-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400/80 live-dot"></span>
                        Live
                    </div>
                    <div class="text-xs text-neutral-400 hidden md:flex items-center gap-1.5 bg-neutral-900 border border-neutral-800 px-2.5 py-1.5 rounded-lg">
                        <i class="fa-solid fa-location-dot text-neutral-500 text-[10px]"></i>
                        <span class="font-medium text-neutral-200"><?php echo htmlspecialchars($user['pincode']); ?></span>
                    </div>
                    <div class="hidden md:flex items-center gap-2.5 pl-1">
                        <div class="w-8 h-8 rounded-full bg-neutral-800 border border-neutral-700 flex items-center justify-center font-semibold text-sm">
                            <?php echo htmlspecialchars(strtoupper(substr($user['username'], 0, 1))); ?>
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <div class="font-medium text-sm"><?php echo htmlspecialchars($user['username']); ?></div>
                            <div class="text-[11px] text-neutral-500 flex items-center gap-2">
                                <span id="header-pos">0 <i class="fa-solid fa-thumbs-up text-[9px]"></i></span>
                                <span id="header-neg">0 <i class="fa-solid fa-thumbs-down text-[9px]"></i></span>
                            </div>
                        </div>
                    </div>
                    <button id="logout-btn" class="text-neutral-500 hover:text-white hover:bg-neutral-900 w-9 h-9 rounded-lg flex items-center justify-center transition-colors" title="Logout">
                        <i class="fa-solid fa-right-from-bracket text-sm"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-[68px] sm:w-60 border-r border-neutral-900 bg-neutral-950 flex flex-col shrink-0">
            <nav class="flex-1 px-2 sm:px-3 py-5 space-y-1">
                <button data-tab="market" class="tab-btn w-full flex items-center px-2 sm:px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200">
                    <i class="fa-solid fa-store text-base sm:mr-3 mx-auto sm:mx-0 w-5 text-center"></i>
                    <span class="hidden sm:block">Marketplace</span>
                </button>
                <button data-tab="my-items" class="tab-btn w-full flex items-center px-2 sm:px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200">
                    <i class="fa-solid fa-box text-base sm:mr-3 mx-auto sm:mx-0 w-5 text-center"></i>
                    <span class="hidden sm:block">My Items</span>
                </button>
                <button data-tab="trades" class="tab-btn w-full flex items-center px-2 sm:px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200">
                    <i class="fa-solid fa-arrow-right-arrow-left text-base sm:mr-3 mx-auto sm:mx-0 w-5 text-center"></i>
                    <span class="hidden sm:block">My Trades</span>
                </button>
                <div class="h-px bg-neutral-900 my-3 mx-1"></div>
                <button data-tab="profile" class="tab-btn w-full flex items-center px-2 sm:px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200">
                    <i class="fa-solid fa-user text-base sm:mr-3 mx-auto sm:mx-0 w-5 text-center"></i>
                    <span class="hidden sm:block">My Profile</span>
                </button>
                <button data-tab="champion" class="tab-btn w-full flex items-center px-2 sm:px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200">
                    <i class="fa-solid fa-trophy text-base sm:mr-3 mx-auto sm:mx-0 w-5 text-center"></i>
                    <span class="hidden sm:block">Champions</span>
                </button>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-neutral-950 p-5 sm:p-8 relative">

            <!-- Marketplace -->
            <div id="tab-market" class="tab-content block">
                <div class="flex justify-between items-center mb-7 gap-4">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight">Local Market</h2>
                        <p class="text-sm text-neutral-500 mt-0.5">Live items from traders in <?php echo htmlspecialchars($user['pincode']); ?></p>
                    </div>
                    <div class="relative shrink-0">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-600 text-xs"></i>
                        <input type="text" id="search-tags" placeholder="Search items or tags..." class="pl-9 pr-4 py-2.5 bg-neutral-900 border border-neutral-800 rounded-xl text-sm focus:outline-none focus:border-neutral-600 text-white placeholder-neutral-600 w-44 sm:w-72 transition-all">
                    </div>
                </div>
                <div id="market-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 pb-20">
                    <div class="col-span-full text-center py-16 text-neutral-600"><i class="fa-solid fa-spinner fa-spin text-2xl"></i></div>
                </div>
            </div>

            <!-- My Items -->
            <div id="tab-my-items" class="tab-content hidden">
                <div class="flex justify-between items-center mb-7">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight">My Items</h2>
                        <p class="text-sm text-neutral-500 mt-0.5">Items you've listed for trade</p>
                    </div>
                    <button onclick="openItemModal()" class="bg-white hover:bg-neutral-200 text-neutral-950 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-plus text-xs"></i> Add Item
                    </button>
                </div>
                <div id="my-items-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 pb-20"></div>
            </div>

            <!-- Trades -->
            <div id="tab-trades" class="tab-content hidden">
                <h2 class="text-xl font-semibold tracking-tight mb-1">My Trades</h2>
                <p class="text-sm text-neutral-500 mb-7">Track and manage your active trades</p>
                <div class="space-y-3" id="trades-list"></div>
            </div>

            <!-- Profile -->
            <div id="tab-profile" class="tab-content hidden">
                <h2 class="text-xl font-semibold tracking-tight mb-7">My Profile</h2>
                <div class="bg-neutral-900/60 border border-neutral-800 p-8 rounded-2xl max-w-2xl">
                    <div class="flex items-center gap-5 mb-8">
                        <div class="w-16 h-16 bg-neutral-800 border border-neutral-700 rounded-2xl flex items-center justify-center text-2xl font-bold">
                            <?php echo htmlspecialchars(strtoupper(substr($user['username'], 0, 1))); ?>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold tracking-tight"><?php echo htmlspecialchars($user['username']); ?></h3>
                            <p class="text-sm text-neutral-500 flex items-center gap-1.5 mt-1">
                                <i class="fa-solid fa-location-dot text-[10px]"></i> Pincode <?php echo htmlspecialchars($user['pincode']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-neutral-950 border border-neutral-800 p-4 rounded-xl">
                            <div class="text-neutral-500 text-xs mb-1.5">Trades done</div>
                            <div class="text-2xl font-bold" id="prof-trades"><i class="fa-solid fa-spinner fa-spin text-sm text-neutral-600"></i></div>
                        </div>
                        <div class="bg-neutral-950 border border-neutral-800 p-4 rounded-xl">
                            <div class="text-neutral-500 text-xs mb-1.5">Positive</div>
                            <div class="text-2xl font-bold flex items-center gap-1.5" id="prof-pos"><i class="fa-solid fa-spinner fa-spin text-sm text-neutral-600"></i></div>
                        </div>
                        <div class="bg-neutral-950 border border-neutral-800 p-4 rounded-xl">
                            <div class="text-neutral-500 text-xs mb-1.5">Negative</div>
                            <div class="text-2xl font-bold text-neutral-500" id="prof-neg"><i class="fa-solid fa-spinner fa-spin text-sm text-neutral-600"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Champions -->
            <div id="tab-champion" class="tab-content hidden">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-3 mb-1">
                        <i class="fa-solid fa-trophy text-neutral-400"></i>
                        <h2 class="text-xl font-semibold tracking-tight">Eco Champions</h2>
                    </div>
                    <p class="text-sm text-neutral-500 mb-7">Top traders in pincode <?php echo htmlspecialchars($user['pincode']); ?> — ranked by reputation. The #1 trader wins a monthly gift.</p>

                    <div id="champion-list" class="space-y-2.5">
                        <div class="h-16 bg-neutral-900/60 border border-neutral-800 rounded-xl animate-pulse"></div>
                        <div class="h-16 bg-neutral-900/60 border border-neutral-800 rounded-xl animate-pulse"></div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Add/Edit Item Modal -->
    <div id="add-item-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-neutral-900 rounded-2xl w-full max-w-md border border-neutral-800 shadow-2xl">
            <div class="px-6 py-5 border-b border-neutral-800 flex justify-between items-center">
                <h3 class="text-base font-semibold" id="item-modal-title">List an Item</h3>
                <button onclick="closeModal('add-item-modal')" class="text-neutral-500 hover:text-white w-8 h-8 rounded-lg hover:bg-neutral-800 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 max-h-[80vh] overflow-y-auto hide-scrollbar">
                <form id="add-item-form" class="space-y-4">
                    <input type="hidden" id="edit-item-id">
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Item title</label>
                        <input type="text" id="item-title" required class="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3.5 py-2.5 text-white text-sm placeholder-neutral-600 focus:border-neutral-500 outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Description</label>
                        <textarea id="item-desc" rows="3" class="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3.5 py-2.5 text-white text-sm placeholder-neutral-600 focus:border-neutral-500 outline-none transition-colors resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Tags <span class="text-neutral-600">(comma separated)</span></label>
                        <input type="text" id="item-tags" placeholder="e.g. books, vintage" class="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3.5 py-2.5 text-white text-sm placeholder-neutral-600 focus:border-neutral-500 outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-300 mb-1.5"><i class="fa-solid fa-bullseye text-[10px] mr-1"></i> Looking for <span class="text-neutral-600">(tags)</span></label>
                        <input type="text" id="item-looking-for" placeholder="e.g. guitar, tools, plant" class="w-full bg-neutral-950 border border-neutral-800 rounded-lg px-3.5 py-2.5 text-white text-sm placeholder-neutral-600 focus:border-neutral-500 outline-none transition-colors">
                        <p class="text-[11px] text-neutral-600 mt-1.5">What you'd like in return. Others can still offer anything.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Image <span id="img-edit-hint" class="text-neutral-600 hidden">(leave blank to keep current)</span></label>
                        <input type="file" id="item-image" accept="image/*" class="w-full text-sm text-neutral-400 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-neutral-800 file:text-neutral-200 hover:file:bg-neutral-700 file:cursor-pointer">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" id="delete-item-btn" class="hidden w-1/3 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 hover:text-white py-2.5 rounded-lg text-sm font-medium transition-colors border border-neutral-700">
                            Delete
                        </button>
                        <button type="submit" class="flex-1 bg-white hover:bg-neutral-200 text-neutral-950 py-2.5 rounded-lg text-sm font-semibold flex justify-center items-center transition-colors">
                            <span id="add-item-btn-text">Post Item</span>
                            <i id="add-item-spinner" class="fa-solid fa-spinner fa-spin hidden ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Trade Propose Modal -->
    <div id="propose-trade-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-neutral-900 rounded-2xl w-full max-w-md border border-neutral-800 shadow-2xl flex flex-col max-h-[90vh]">
            <div class="px-6 py-5 border-b border-neutral-800 flex justify-between items-center shrink-0">
                <h3 class="text-base font-semibold">Propose a Trade</h3>
                <button onclick="closeModal('propose-trade-modal')" class="text-neutral-500 hover:text-white w-8 h-8 rounded-lg hover:bg-neutral-800 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto hide-scrollbar">
                <p class="text-sm text-neutral-400 mb-1">You want: <span id="wanted-item-title" class="text-white font-semibold"></span></p>
                <p class="text-[12px] text-neutral-500 mb-5" id="wanted-looking-for"></p>
                <p class="text-xs font-medium text-neutral-400 mb-2.5">Select an item to offer in return</p>
                <div id="offer-items-list" class="space-y-2"></div>
                <input type="hidden" id="wanted-item-id">
            </div>
            <div class="px-6 py-5 border-t border-neutral-800 shrink-0">
                <button id="submit-trade-btn" disabled class="w-full bg-white hover:bg-neutral-200 text-neutral-950 disabled:opacity-30 disabled:cursor-not-allowed py-2.5 rounded-lg text-sm font-semibold transition-colors">
                    Send request
                </button>
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="rating-modal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-neutral-900 rounded-2xl w-full max-w-sm border border-neutral-800 shadow-2xl text-center p-8">
            <h3 class="text-lg font-semibold mb-1.5">Trade completed</h3>
            <p class="text-sm text-neutral-400 mb-7">Rate your experience with this trader. This is required to finalize.</p>
            <input type="hidden" id="rating-trade-id">

            <div class="grid grid-cols-3 gap-2.5 mb-6">
                <button onclick="submitRating(1)" class="p-4 rounded-xl border border-neutral-800 bg-neutral-950 hover:border-white hover:bg-neutral-800 transition-all flex flex-col items-center gap-2 group">
                    <i class="fa-solid fa-thumbs-up text-xl text-neutral-500 group-hover:text-white transition-colors"></i>
                    <span class="text-[11px] font-medium text-neutral-400 group-hover:text-white">Positive</span>
                </button>
                <button onclick="submitRating(0)" class="p-4 rounded-xl border border-neutral-800 bg-neutral-950 hover:border-neutral-600 hover:bg-neutral-800 transition-all flex flex-col items-center gap-2 group">
                    <i class="fa-solid fa-minus text-xl text-neutral-500 group-hover:text-neutral-200 transition-colors"></i>
                    <span class="text-[11px] font-medium text-neutral-400 group-hover:text-neutral-200">Neutral</span>
                </button>
                <button onclick="submitRating(-1)" class="p-4 rounded-xl border border-neutral-800 bg-neutral-950 hover:border-neutral-500 hover:bg-neutral-800 transition-all flex flex-col items-center gap-2 group">
                    <i class="fa-solid fa-thumbs-down text-xl text-neutral-500 group-hover:text-neutral-300 transition-colors"></i>
                    <span class="text-[11px] font-medium text-neutral-400 group-hover:text-neutral-300">Negative</span>
                </button>
            </div>
            <p class="text-[11px] text-neutral-600">Leaving a rating finalizes the trade for both parties.</p>
        </div>
    </div>

    <!-- Trade Chat Modal -->
    <div id="trade-chat-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[40] hidden flex justify-end">
        <div class="bg-neutral-950 border-l border-neutral-800 w-full sm:w-[440px] h-full flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300" id="chat-panel">
            <div class="px-5 py-4 border-b border-neutral-800 flex justify-between items-center bg-neutral-900 shrink-0">
                <div>
                    <h3 class="text-base font-semibold">Trade Chat</h3>
                    <p class="text-xs text-neutral-500" id="chat-status-text">Status</p>
                </div>
                <button onclick="closeChat()" class="text-neutral-500 hover:text-white bg-neutral-800 hover:bg-neutral-700 w-8 h-8 rounded-lg flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="px-5 py-3.5 bg-neutral-900/50 border-b border-neutral-800 shrink-0 flex justify-between items-center gap-3">
                <div class="text-sm text-neutral-300" id="chat-trade-details"></div>
                <div id="chat-actions"></div>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-3" id="chat-messages"></div>

            <div class="p-4 border-t border-neutral-800 bg-neutral-950 shrink-0">
                <form id="chat-form" class="flex gap-2">
                    <input type="hidden" id="chat-trade-id">
                    <input type="text" id="chat-input" required placeholder="Type a message..." class="flex-1 bg-neutral-900 border border-neutral-800 rounded-lg px-3.5 py-2.5 text-white text-sm placeholder-neutral-600 focus:border-neutral-600 outline-none transition-colors" autocomplete="off">
                    <button type="submit" class="bg-white hover:bg-neutral-200 text-neutral-950 w-10 rounded-lg flex items-center justify-center transition-colors"><i class="fa-solid fa-arrow-up text-sm"></i></button>
                </form>
            </div>
        </div>
    </div>

    <script>const CURRENT_USER_ID = <?php echo json_encode($user['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="assets/app.js"></script>
</body>
</html>

