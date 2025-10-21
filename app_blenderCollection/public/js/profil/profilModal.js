export default function initModalScript() {
        /* Modale Édition Générique */
        const dynamicModal = document.getElementById('genericEditModal');
        const fieldContainer = dynamicModal.querySelector('#genericModalField');
        const modalTitle = dynamicModal.querySelector('#genericModalTitle');
        const modalForm = dynamicModal.querySelector('form');

        const domElements = {
            dynamicModal: 'genericEditModal',
            modalTitle: '#genericModalTitle',
            modalForm: 'form',
            fieldContainer: '#genericModalField'
        };

        const refs = {};
        let hasError = false;

        for (const [key, selector] of Object.entries(domElements)) {
            const el = key === 'dynamicModal'
                ? document.getElementById(selector)
                : document.querySelector(selector);

            if (!el) {
                console.error(` Élément manquant : ${selector}`);
                hasError = true;
            } else {
                refs[key] = el;
            }
        }

        if (hasError) {
            console.error(" Le script est interrompu à cause d’éléments DOM manquants.");
            return;
        } else console.log("Script profilModal.js chargé ");

        window.openModal = function ({ title, action, fieldName, value }) {
            modalTitle.textContent = title;
            modalForm.action = action;
            fieldContainer.innerHTML = "";

            if (fieldName === "description") {
                fieldContainer.innerHTML = `
                    <textarea 
                        name="${fieldName}" 
                        rows="10"
                        class="w-full px-4 py-2 border rounded-md bg-white text-black-950 resize-none"
                        required
                    >${value}</textarea>`;
            } else {
                fieldContainer.innerHTML = `
                    <input 
                        type="text" 
                        name="${fieldName}" 
                        value="${value}" 
                        class="w-full px-4 py-2 border rounded-md bg-white text-black-950"
                        required
                    />`;
            }

            dynamicModal.classList.remove('hidden');

            gsap.fromTo(dynamicModal.querySelector("div"),
                { y: -20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.3, ease: "power2.out" }
            );
        };

        // Fermer la modale en cliquant en dehors
        dynamicModal.addEventListener("click", (e) => {
            const content = dynamicModal.querySelector("div");
            if (!content.contains(e.target)) {
                dynamicModal.classList.add("hidden");
            }
        });

        // Support du système `data-*`
        document.querySelectorAll('.open-modal-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const { title, action, fieldName, value } = btn.dataset;

                window.openModal({
                    title,
                    action,
                    fieldName,
                    value
                });
            });
        });
}
