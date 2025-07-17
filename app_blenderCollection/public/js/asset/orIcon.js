export default function initAddonScript(val) {
    console.log("Script orIcon.js chargé ✅");
    
  const cards = document.querySelectorAll(".tilt-card");

  cards.forEach(card => {
    card.style.transformStyle = "preserve-3d";
    card.style.transformOrigin = "center";

    card.addEventListener("mousemove", (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      const centerX = rect.width / 2;
      const centerY = rect.height / 2;

      const rotateX = ((y - centerY) / centerY) * -val; // inversion verticale
      const rotateY = ((x - centerX) / centerX) * val;

      gsap.to(card, {
        rotateX: rotateX,
        rotateY: rotateY,
        duration: 0.4,
        ease: "power2.out"
      });
    });

    card.addEventListener("mouseleave", () => {
      gsap.to(card, {
        rotateX: 0,
        rotateY: 0,
        duration: 0.8,
        ease: "power2.out"
      });
    });
  });
}