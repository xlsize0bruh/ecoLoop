<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header id="main-header" class="sticky top-0 w-full border-b border-neutral-900 bg-black z-50 shrink-0 transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 w-full">
        <div class="flex justify-between items-center h-16">
            
            <div id="left-nav" class="flex items-center border border-transparent transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)]">
                <a href="index.php?home=1" class="flex items-center gap-2.5" aria-label="EcoLoop home">
                    <img src="assets/favicon.svg" alt="" class="w-8 h-8 rounded-[9px]">
                    <span class="font-semibold text-[15px] tracking-tight hidden sm:block text-white">EcoLoop</span>
                </a>
            </div>

            <nav id="center-nav" class="absolute left-1/2 -translate-x-1/2 flex items-center rounded-2xl border border-neutral-800 bg-neutral-900 p-1.5 transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)] shadow-sm" aria-label="EcoLoop sections">
                <a href="dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'bg-neutral-700 text-white shadow-sm' : 'text-neutral-400 hover:text-white hover:bg-neutral-800/50'; ?> px-4 sm:px-5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors duration-300 ease-out">Marketplace</a>
                <a href="projects.php" data-view="studio" class="<?php echo $currentPage == 'projects.php' ? 'bg-neutral-700 text-white shadow-sm' : 'text-neutral-400 hover:text-white hover:bg-neutral-800/50'; ?> px-4 sm:px-5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors duration-300 ease-out">Projects</a>
                <a href="projects.php#projects" data-view="projects" class="text-neutral-400 hover:text-white hover:bg-neutral-800/50 px-4 sm:px-5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors duration-300 ease-out">Mine</a>
                <a href="projects.php#credits" data-view="credits" class="text-neutral-400 hover:text-white hover:bg-neutral-800/50 px-4 sm:px-5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors duration-300 ease-out">Credits</a>
            </nav>

            <div id="right-nav" class="flex items-center gap-3 border border-transparent transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)]">
                <div class="hidden sm:flex items-center gap-1.5 text-xs text-neutral-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400/80 animate-pulse"></span>
                    Live
                </div>
                <div class="text-xs text-neutral-400 hidden md:flex items-center gap-1.5 bg-neutral-900 border border-neutral-800 px-2.5 py-1.5 rounded-lg">
                    <i class="fa-solid fa-location-dot text-neutral-500 text-[10px]"></i>
                    <span class="font-medium text-neutral-200"><?php echo htmlspecialchars($user['pincode']); ?></span>
                </div>
                
                <?php if (!$isGuest): ?>
                <div class="hidden md:flex items-center gap-2.5 pl-1 user-ui">
                    <div class="w-8 h-8 rounded-full bg-neutral-800 border border-neutral-700 flex items-center justify-center font-semibold text-sm text-white">
                        <?php echo htmlspecialchars(strtoupper(substr($user['username'], 0, 1))); ?>
                    </div>
                    <div class="hidden sm:block leading-tight text-white">
                        <div class="font-medium text-sm"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="text-[11px] text-neutral-500 flex items-center gap-2">
                            <span id="header-pos">0 <i class="fa-solid fa-thumbs-up text-[9px]"></i></span>
                            <span id="header-neg">0 <i class="fa-solid fa-thumbs-down text-[9px]"></i></span>
                        </div>
                    </div>
                </div>
                <a href="logout.php" id="logout-btn" class="text-neutral-500 hover:text-white hover:bg-neutral-900 w-9 h-9 rounded-lg flex items-center justify-center transition-colors user-ui" title="Logout">
                    <i class="fa-solid fa-right-from-bracket text-sm"></i>
                </a>
                <?php else: ?>
                    <a href="index.php#login" class="bg-white text-black px-4 py-2 rounded-lg text-sm font-semibold ml-2 hover:bg-neutral-200 transition-colors guest-ui">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<link rel="stylesheet" href="https://unpkg.com/lenis@1.3.26/dist/lenis.css">
<script src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const header = document.getElementById('main-header');
    const leftNav = document.getElementById('left-nav');
    const rightNav = document.getElementById('right-nav');
    const scrollContainer = document.getElementById('main-content') || window;
    
    if (header) {
        scrollContainer.addEventListener('scroll', function() {
            const currentScroll = scrollContainer === window ? window.scrollY : scrollContainer.scrollTop;
            if (currentScroll > 20) {
                header.classList.remove('bg-black', 'border-b', 'border-neutral-900');
                header.classList.add('bg-transparent', 'border-transparent', 'pt-3');
                
                leftNav.classList.add('bg-neutral-900', 'border', 'border-neutral-800', 'rounded-2xl', 'px-4', 'py-2', 'shadow-sm');
                rightNav.classList.add('bg-neutral-900', 'border', 'border-neutral-800', 'rounded-2xl', 'px-3', 'py-1.5', 'shadow-sm');
            } else {
                header.classList.add('bg-black', 'border-b', 'border-neutral-900');
                header.classList.remove('bg-transparent', 'border-transparent', 'pt-3');
                
                leftNav.classList.remove('bg-neutral-900', 'border', 'border-neutral-800', 'rounded-2xl', 'px-4', 'py-2', 'shadow-sm');
                rightNav.classList.remove('bg-neutral-900', 'border', 'border-neutral-800', 'rounded-2xl', 'px-3', 'py-1.5', 'shadow-sm');
            }
        });
    }

    // Lenis Smooth Scroll
    const lenisWrapper = document.querySelector('main.overflow-y-auto');
    window.lenis = new Lenis(lenisWrapper ? { wrapper: lenisWrapper, content: lenisWrapper.firstElementChild || lenisWrapper } : {});

    function raf(time) {
        window.lenis.raf(time);
        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);
});
</script>
