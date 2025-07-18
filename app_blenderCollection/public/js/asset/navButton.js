

export default function initModalScript() {
        const btn = document.getElementById("createCollectionBtn");

        if(!btn){
            console.error("Script navButton.js ne sait pas chargé ⚠️");
            return;
        } 

        console.log("Script navButton.js chargé ✅");


        // Animation survol (hover)
        btn.addEventListener("mouseenter", () => {
            gsap.to(btn, {
            y: -5,
            boxShadow: "0 10px 20px rgba(113, 214, 255, 0.3)",
            duration: 0.3,
            ease: "power2.out"
            });
        });

        // Retour normal
        btn.addEventListener("mouseleave", () => {
            gsap.to(btn, {
            y: 0,
            boxShadow: "0 4px 6px rgba(0, 0, 0, 0.1)",
            duration: 0.3,
            ease: "power2.inOut"
            });
        });

        // Clic pulsé
        btn.addEventListener("mousedown", () => {
            gsap.to(btn, {
            scale: 0.95,
            duration: 0.1
            });
        });

        btn.addEventListener("mouseup", () => {
            gsap.to(btn, {
            scale: 1,
            duration: 0.2,
            ease: "elastic.out(1, 0.4)"
            });
        });

        const addHoverAnim = (el, color) => {
            el.addEventListener("mouseenter", () => {
            gsap.to(el, {
                y: -4,
                scale: 1.05,
                duration: 0.3,
                ease: "power2.out",
                color: color,
                boxShadow: `0 4px 12px ${color}40` // légère lueur colorée
            });
            });

            el.addEventListener("mouseleave", () => {
            gsap.to(el, {
                y: 0,
                scale: 1,
                duration: 0.3,
                ease: "power2.inOut",
                color: "", // reset
                boxShadow: "none"
            });
            });
        };

        const profil = document.getElementById("btnProfil");
        const logout = document.getElementById("btnLogout");

        addHoverAnim(profil, "#3B82F6");  // bleu Tailwind
        addHoverAnim(logout, "#EF4444");  // rouge Tailwind
}