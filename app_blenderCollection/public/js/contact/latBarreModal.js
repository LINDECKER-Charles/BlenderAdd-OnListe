export default function initModalScript() {
        const modal = document.getElementById('modal');
        const modalContent = document.getElementById('modal-content');
        const closeBtn = document.getElementById('modal-close');

        if (!modal || !modalContent || !closeBtn){
            console.log("Script latBarreModal.js non chargé");
            return;
        }

        console.log("Script latBarreModal.js chargé ✅");
        
        /* Affichage du modal */
        document.querySelectorAll('[data-modal-id]').forEach(button => {
            button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-modal-id');
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                modalContent.innerHTML = targetElement.innerHTML;
                modal.classList.remove('hidden');

                gsap.fromTo(modal,
                    { y: -20, opacity: 0 },
                    { y: 0, opacity: 1, duration: 0.3, ease: "power2.out" }
                );
            }
            });
        });

        /* Ferme le modal et le vide quand button fermeture cliqué */
        closeBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            modalContent.innerHTML = '';
        });

        //Fermer la modale au clic en dehors
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
            modal.classList.add('hidden');
            modalContent.innerHTML = '';
            }
        });
}