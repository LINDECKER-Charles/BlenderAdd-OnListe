export default function initModalScript() {
    document.addEventListener('turbo:load', () => {
        console.log("Script flashFade.js chargé ✅");
        document.querySelectorAll('.flash-dismissable').forEach((el) => {
            setTimeout(() => {
            el.classList.remove('opacity-100');
            el.classList.add('opacity-0');
            setTimeout(() => el.remove(), 500); // correspond à duration-500
            }, 4000);
        });    
    });
}
                
