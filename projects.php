<?php
session_start();
$isGuest = !isset($_SESSION['user']);
$user = $isGuest ? ['username' => 'Guest', 'pincode' => 'Anywhere'] : $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects — EcoLoop</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="https://unpkg.com/lenis@1.3.26/dist/lenis.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap');
        #heroTitle {
            font-family: 'Playfair Display', serif;
        }
        .grid>div {
            grid-row: var(--r);
            grid-column: var(--c);
        }

        img {
            display: block;
            width: 100%;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="w-full bg-[#F4F6F1]">

        <div class="grid grid-cols-8 grid-rows-20 gap-0.5 overflow-hidden">
            <div class="elem col-span-1 row-span-1" style="--r: 1; --c: 3;"><img src="assets/images/img1.jpg" alt="Image 1"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 2; --c: 1;"><img src="assets/images/img2.jpg" alt="Image 2"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 2; --c: 7;"><img src="assets/images/img3.jpg" alt="Image 3"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 3; --c: 2;"><img src="assets/images/img4.jpg" alt="Image 4"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 3; --c: 6;"><img src="assets/images/img5.jpg" alt="Image 5"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 4; --c: 4;"><img src="assets/images/img6.jpg" alt="Image 6"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 5; --c: 8;"><img src="assets/images/img7.jpg" alt="Image 7"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 6; --c: 5;"><img src="assets/images/img8.jpg" alt="Image 8"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 6; --c: 2;"><img src="assets/images/img9.jpg" alt="Image 9"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 7; --c: 4;"><img src="assets/images/img10.jpg" alt="Image 10"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 8; --c: 6;"><img src="assets/images/img11.jpg" alt="Image 11"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 8; --c: 3;"><img src="assets/images/img12.jpg" alt="Image 12"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 9; --c: 7;"><img src="assets/images/img13.jpg" alt="Image 13"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 10; --c: 5;"><img src="assets/images/img14.jpg" alt="Image 14"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 10; --c: 2;"><img src="assets/images/img15.jpg" alt="Image 15"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 11; --c: 5;"><img src="assets/images/img16.jpg" alt="Image 16"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 12; --c: 3;"><img src="assets/images/img17.jpg" alt="Image 17"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 13; --c: 7;"><img src="assets/images/img18.jpg" alt="Image 18"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 9; --c: 1;"><img src="assets/images/img19.jpg" alt="Image 19"></div>
            <div class="elem col-span-1 row-span-1" style="--r: 10; --c: 7;"><img src="assets/images/img20.jpg" alt="Image 20"></div>
        </div>

        <div id="hero" class="fixed top-0 left-0 w-full h-full flex flex-col items-center justify-center pointer-events-none z-40">

            <h1 id="heroTitle" class="text-9xl font-extrabold italic text-black mb-4">
                Ecoloop
            </h1>

            <h2 id="heroSubtitle" class="text-2xl font-medium tracking-wide text-[#202823]">
                Make more, Waste less.
            </h2>

        </div>

        <div id="about" class="w-full min-h-screen mx-auto flex flex-col items-center justify-center relative z-10 text-center space-y-12">

            <p class="text-black text-4xl w-3/4 font-regular leading-relaxed">
                Make more, waste less. Turn nearby materials into your next idea.
                EcoLoop helps you discover, reuse, and transform materials that would
                otherwise go to waste—giving everyday waste a second life through
                creativity and practical action.
            </p>

            <a href="#" class="group inline-flex items-center justify-center bg-white text-[#051F20] pl-8 pr-2 py-2 rounded-full font-bold text-lg tracking-[0.15em] hover:tracking-[0.4em] transition-all duration-300 ease-out shadow-[0_10px_40px_rgba(0,0,0,0.1)] hover:shadow-[0_20px_50px_rgba(0,0,0,0.2)] hover:-translate-y-1">
                <span class="whitespace-nowrap transition-all duration-300 ease-out uppercase">Create Project</span>
                
                <div class="ml-5 flex items-center justify-center bg-[#051F20] text-white w-12 h-12 rounded-[14px] transition-all duration-300 ease-out group-hover:scale-110">
                    <svg class="w-5 h-5 transition-transform duration-300 ease-out group-hover:translate-x-1 group-hover:-translate-y-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </a>

        </div>

    </div>

    <svg style="position:absolute;width:0;height:0">
        <filter id="liquid">
            <feTurbulence
                type="fractalNoise"
                baseFrequency="0.006 0.025"
                numOctaves="2"
                seed="4"
                result="noise">
            </feTurbulence>
    
            <feDisplacementMap
                in="SourceGraphic"
                in2="noise"
                scale="0">
            </feDisplacementMap>
        </filter>
    </svg>

    <?php include 'auth_modal.php'; ?>
    <script src="Script.js"></script>
    <script src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="assets/Style.js"></script>
</body>
</html>
