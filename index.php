<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoLoop — Trade, build, and reuse locally</title>
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
        html { scroll-behavior: smooth; }
        .grid-fade {
            background-image: linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%);
        }
    </style>
</head>
<body class="bg-neutral-950 text-neutral-100 font-sans antialiased selection:bg-white selection:text-neutral-950">

    <!-- Navbar -->
    <nav class="border-b border-neutral-900 bg-neutral-950/70 backdrop-blur-xl fixed w-full z-50">
        <div class="max-w-6xl mx-auto px-5 sm:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="index.php" class="flex items-center gap-2.5" aria-label="EcoLoop home">
                    <img src="assets/favicon.svg" alt="" class="w-8 h-8 rounded-[9px]">
                    <span class="font-semibold text-[15px] tracking-tight">EcoLoop</span>
                </a>
                <div class="flex items-center gap-2">
                    <div class="hidden sm:flex items-center rounded-xl border border-neutral-800 bg-neutral-900/70 p-1 mr-2" aria-label="Explore EcoLoop">
                        <a href="login.php" class="bg-neutral-800 text-white px-3 py-1.5 rounded-lg text-sm">Marketplace</a>
                        <a href="projects.php" class="text-neutral-400 hover:text-white px-3 py-1.5 rounded-lg text-sm">Projects</a>
                    </div>
                    <a href="login.php" class="text-neutral-400 hover:text-white px-3.5 py-2 rounded-lg text-sm font-medium transition-colors">Sign in</a>
                    <a href="login.php?signup=1" class="bg-white hover:bg-neutral-200 text-neutral-950 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Get started</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <header class="relative pt-40 pb-28 overflow-hidden">
        <div class="absolute inset-0 grid-fade"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-neutral-800/20 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="relative max-w-3xl mx-auto px-5 sm:px-8 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 mb-8 rounded-full border border-neutral-800 bg-neutral-900/50 text-xs text-neutral-400">
                <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                Hyper-local, waste-free trading
            </div>
            <h1 class="text-5xl md:text-6xl font-extrabold tracking-tight leading-[1.05] mb-6">
                Trade local.<br>
                <span class="text-neutral-500">Save global.</span>
            </h1>
            <p class="max-w-xl mx-auto text-lg text-neutral-400 mb-10 leading-relaxed">
                Turn your unused items into something you actually need. Connect with neighbors in your pincode, trade securely, and build a trusted reputation.
            </p>
            <div class="flex justify-center gap-3">
                <a href="login.php?signup=1" class="bg-white hover:bg-neutral-200 text-neutral-950 px-6 py-3 rounded-xl text-[15px] font-semibold transition-all">
                    Start trading
                </a>
                <a href="#features" class="bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 text-neutral-200 px-6 py-3 rounded-xl text-[15px] font-medium transition-all">
                    Learn more
                </a>
            </div>
        </div>
    </header>

    <!-- Features -->
    <section id="features" class="py-24 border-t border-neutral-900">
        <div class="max-w-6xl mx-auto px-5 sm:px-8">
            <div class="max-w-xl mb-16">
                <h2 class="text-3xl font-bold tracking-tight mb-3">Why EcoLoop?</h2>
                <p class="text-neutral-400 text-lg">A simple, minimal way to reduce waste and get what you want.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="group bg-neutral-900/60 p-7 rounded-2xl border border-neutral-800 hover:border-neutral-700 transition-colors">
                    <div class="w-11 h-11 bg-neutral-800 rounded-xl flex items-center justify-center mb-5 group-hover:bg-white transition-colors">
                        <i class="fa-solid fa-location-dot text-neutral-300 group-hover:text-neutral-950 transition-colors"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Hyper-local matching</h3>
                    <p class="text-neutral-400 text-sm leading-relaxed">Sign up with your pincode. We only show items from people in your immediate neighborhood, so physical trades are effortless.</p>
                </div>
                <div class="group bg-neutral-900/60 p-7 rounded-2xl border border-neutral-800 hover:border-neutral-700 transition-colors">
                    <div class="w-11 h-11 bg-neutral-800 rounded-xl flex items-center justify-center mb-5 group-hover:bg-white transition-colors">
                        <i class="fa-solid fa-comments text-neutral-300 group-hover:text-neutral-950 transition-colors"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Secure trading &amp; chat</h3>
                    <p class="text-neutral-400 text-sm leading-relaxed">Propose trades, chat in real time, and agree on a meeting spot to exchange items. Every trade builds your reputation.</p>
                </div>
                <div class="group bg-neutral-900/60 p-7 rounded-2xl border border-neutral-800 hover:border-neutral-700 transition-colors">
                    <div class="w-11 h-11 bg-neutral-800 rounded-xl flex items-center justify-center mb-5 group-hover:bg-white transition-colors">
                        <i class="fa-solid fa-trophy text-neutral-300 group-hover:text-neutral-950 transition-colors"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Monthly champions</h3>
                    <p class="text-neutral-400 text-sm leading-relaxed">The trader with the best reputation in each pincode tops the leaderboard and wins a monthly eco-gift.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 border-t border-neutral-900">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 grid md:grid-cols-2 gap-16 items-center">
            <div><span class="text-xs uppercase tracking-[.2em] text-lime-300">EcoLoop Project Studio</span>
            <h2 class="text-4xl md:text-5xl font-bold tracking-tight mt-5 mb-6">Trade what you have.<br><span class="text-neutral-500">Build what you need.</span></h2>
            <p class="text-neutral-400 leading-relaxed mb-8">One idea. Materials from your community. Find the pieces for your next project, and give every contributor something useful in return.</p>
            <a href="projects.php" class="inline-block bg-white text-neutral-950 px-6 py-3 rounded-xl font-semibold">Enter the Project Studio ↗</a></div>
            <div class="border border-neutral-800 bg-neutral-900/50 rounded-2xl p-8"><span class="text-xs uppercase tracking-widest text-neutral-500">An illustrative 60-credit exchange</span>
            <div class="flex justify-between border-b border-neutral-800 py-5"><span>Asha · cardboard</span><span class="text-lime-300">+20 cr</span></div>
            <div class="flex justify-between border-b border-neutral-800 py-5"><span>Kabir · tubes</span><span class="text-lime-300">+15 cr</span></div>
            <div class="flex justify-between border-b border-neutral-800 py-5"><span>Mira · fabric &amp; string</span><span class="text-lime-300">+25 cr</span></div>
            <p class="text-neutral-400 text-sm mt-6">You collect one complete kit. They choose useful goods from the community shelf or other participating listings.</p></div>
        </div>
    </section>
    <!-- CTA -->
    <section class="py-24 border-t border-neutral-900">
        <div class="max-w-3xl mx-auto px-5 sm:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold tracking-tight mb-5">Ready to trade?</h2>
            <p class="text-neutral-400 text-lg mb-9">Join your neighborhood marketplace in under a minute.</p>
            <a href="login.php?signup=1" class="inline-block bg-white hover:bg-neutral-200 text-neutral-950 px-7 py-3.5 rounded-xl text-[15px] font-semibold transition-all">
                Create your account
            </a>
        </div>
    </section>

    <footer class="border-t border-neutral-900 py-10">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 flex items-center justify-between text-sm text-neutral-500">
            <div class="flex items-center gap-2">
                <img src="assets/favicon.svg" alt="" class="w-6 h-6 rounded-md">
                <span>EcoLoop</span>
            </div>
            <span>Trade local, save global.</span>
        </div>
    </footer>
</body>
</html>

