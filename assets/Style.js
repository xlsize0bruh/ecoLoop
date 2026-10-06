document.addEventListener('DOMContentLoaded', () => {
    gsap.registerPlugin(ScrollTrigger);
    const lenis = new Lenis({
        duration: 1.5,
        smoothWheel: true,
        smoothTouch: false,
        wheelMultiplier: 0.8
    });

    lenis.on("scroll", ScrollTrigger.update);
    gsap.ticker.add((time) => {
        lenis.raf(time * 1000);
    });
    gsap.ticker.lagSmoothing(0);
    const displacement = document.querySelector("#liquid feDisplacementMap");

    gsap.set("#heroTitle", {
        filter: "url(#liquid)",
        transformOrigin: "50% 50%"
    });

    gsap.set("#heroSubtitle", {
        filter: "url(#liquid)",
        transformOrigin: "50% 50%"
    });

    const tl = gsap.timeline({
        scrollTrigger: {
            trigger: "#about",
            start: "top 90%",
            end: "top 20%",
            scrub: 2
        }
    });

    tl.to(displacement, {
        attr: {
            scale: 25
        },
        ease: "sine.out"
    }, 0);

    tl.to("#heroTitle", {
        y: -20,
        scaleX: 1.06,
        scaleY: 0.96,
        skewX: -1,
        opacity: 0.9,
        ease: "sine.out"
    }, 0);

    tl.to("#heroSubtitle", {
        y: -10,
        scaleX: 1.02,
        skewX: 1,
        opacity: 0.92,
        ease: "sine.out"
    }, 0.02);

    tl.to(displacement, {
        attr: {
            scale: 65
        },
        ease: "sine.inOut"
    }, 0.35);

    tl.to("#heroTitle", {
        y: -45,
        scaleX: 1.12,
        scaleY: 0.9,
        skewX: -3,
        opacity: 0.6,
        ease: "sine.inOut"
    }, 0.35);

    tl.to("#heroSubtitle", {
        y: -28,
        scaleX: 1.05,
        skewX: 2,
        opacity: 0.7,
        ease: "sine.inOut"
    }, 0.38);

    tl.to(displacement, {
        attr: {
            scale: 100
        },
        ease: "sine.inOut"
    }, 0.65);

    tl.to("#heroTitle", {
        y: -70,
        scaleX: 1.17,
        scaleY: 0.84,
        skewX: -5,
        opacity: 0,
        filter: "url(#liquid) blur(2px)",
        ease: "sine.in"
    }, 0.65);

    tl.to("#heroSubtitle", {
        y: -48,
        scaleX: 1.08,
        scaleY: 0.94,
        skewX: 3,
        opacity: 0,
        filter: "url(#liquid) blur(1px)",
        ease: "sine.in"
    }, 0.7);

    document.querySelectorAll(".elem").forEach(elem => {

        let image = elem.querySelector("img");

        let xTransform = gsap.utils.random(-40, 40);

        gsap.set(image, {
            transformOrigin: `${xTransform < 0 ? "0%" : "100%"} 50%`
        });

        gsap.to(image, {
            scale: 0,
            ease: "none",
            scrollTrigger: {
                trigger: image,
                start: "top top",
                end: "bottom top",
                scrub: 1
            }
        });

        gsap.to(elem, {
            xPercent: xTransform,
            ease: "power2.inOut",
            scrollTrigger: {
                trigger: image,
                start: "top bottom",
                end: "bottom top",
                scrub: 1
            }
        });

    });
});