export default function initAddonScript() {
    console.log("Script upArrow.js chargé ✅");
    
  const arrow = document.getElementById("scroll-arrow");
  const path = arrow.querySelector("path");
  const length = path.getTotalLength();

  // Init du stroke invisible
  gsap.set(path, {
    strokeDasharray: length,
    strokeDashoffset: 0
  });

  // Hover : grossit légèrement
  arrow.addEventListener("mouseenter", () => {
    gsap.to(arrow, {
      scale: 1.1,
      duration: 0.2,
      ease: "power1.out"
    });
  });

  arrow.addEventListener("mouseleave", () => {
    gsap.to(arrow, {
      scale: 1,
      duration: 0.2,
      ease: "power1.inOut"
    });
  });

  // Click : effet de tracé
  arrow.addEventListener("click", () => {
    gsap.fromTo(
      path,
      { strokeDashoffset: length },
      {
        strokeDashoffset: 0,
        duration: 1.2,
        ease: "power2.out"
      }
    );
  });
}