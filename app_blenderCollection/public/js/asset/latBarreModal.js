export default function initModalScript() {

    
    document.addEventListener('turbo:load', () => {

        const modal = document.getElementById('modal');
        const modalContent = document.getElementById('modal-content');
        const closeBtn = document.getElementById('modal-close');

        if (!modal || !modalContent || !closeBtn)return;

        console.log("Script latBarreModal.js chargé ✅");
        
        document.querySelectorAll('[data-modal-id]').forEach(button => {
            button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-modal-id');
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                modalContent.innerHTML = targetElement.innerHTML;
                modal.classList.remove('hidden');
            }
            });
        });

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
    });
}