
export default function initModalScript() {
    document.addEventListener('turbo:load', () => {
        const isDesktop = window.innerWidth >= 1024;
        const sidebar = document.getElementById("adminSidebar");
        const openBtn = document.getElementById("toggleSidebarBtn");
        const closeBtn = document.getElementById("closeSidebarBtn");
        const modal = document.getElementById("customConfirmModal");
        const confirmMessage = document.getElementById("confirmMessage");
        const confirmBtn = document.getElementById("confirmAction");
        const cancelBtn = document.getElementById("cancelConfirm");

        const domElements = {
            sidebar: "adminSidebar",
            openBtn: "toggleSidebarBtn",
            closeBtn: "closeSidebarBtn",
            modal: "customConfirmModal",
            confirmMessage: "confirmMessage",
            confirmBtn: "confirmAction",
            cancelBtn: "cancelConfirm"
        };


        const refs = {};
        let hasError = false;
        for (const [key, id] of Object.entries(domElements)) {
            const el = document.getElementById(id);
            if (!el) {
                console.error(`❌ Élément manquant : #${id}`);
                hasError = true;
            } else {
                refs[key] = el;
            }
        }

        if (hasError) {
            console.error("💥 Le script est interrompu à cause d’éléments DOM manquants.");
            return;
        }else console.log("Script profilSideBarre.js chargé ✅");

        /* Ouvre la side barre */
        const showSidebar = () => {
            openBtn.classList.add("opacity-0", "pointer-events-none");
            sidebar.classList.remove("hidden");
            gsap.fromTo(sidebar, { x: 300, opacity: 0 }, { x: 0, opacity: 1, duration: 0.3, ease: "power2.out" });
        };openBtn.addEventListener("click", showSidebar);

        if(isDesktop)showSidebar(); 
        /* Ferme la side barre */
        const hideSidebar = () => {
            gsap.to(sidebar, {
                x: 300,
                opacity: 0,
                duration: 0.3,
                ease: "power2.in",
                onComplete: () => {
                sidebar.classList.add("hidden");
                openBtn.classList.remove("opacity-0", "pointer-events-none");
                }
            });
        };closeBtn.addEventListener("click", hideSidebar);



        let currentForm = null;

        // Attache les événements sur les formulaires avec [data-confirm]
        document.querySelectorAll("form[data-confirm]").forEach(form => {
            form.addEventListener("submit", (e) => {
                e.preventDefault(); // empêche l'envoi direct
                currentForm = form;
                confirmMessage.innerText = form.dataset.confirm || "Es-tu sûr de vouloir continuer ?";
                showModal();
            });
        });

        cancelBtn.addEventListener("click", () => {
            hideModal();
        });

        confirmBtn.addEventListener("click", () => {
            if (currentForm) {
                currentForm.submit();
            }
            hideModal();
        });

        function showModal() {
            modal.classList.remove("hidden");
            gsap.fromTo(modal.querySelector("div"),
                { y: -30, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.3, ease: "power2.out" }
            );
        }

        function hideModal() {
            gsap.to(modal.querySelector("div"),
                {
                y: -30, opacity: 0, duration: 0.2, ease: "power2.in", onComplete: () => {
                    modal.classList.add("hidden");
                }
                }
            );
        }

        modal.addEventListener("click", (e) => {
            const content = modal.querySelector("div");
            if (!content.contains(e.target)) {
                hideModal();
            }
        });
        


        /* A factoriser */
        const avatarImg = document.querySelector('#upload-avatar-form img');
        const svgIcon = document.querySelector('#upload-avatar-form svg');

        if (!avatarImg || !svgIcon) return;

        const img = new Image();
        img.crossOrigin = "anonymous";
        img.src = avatarImg.src;

        img.onload = function () {
        const canvas = document.createElement("canvas");
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;

        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        let r, g, b, totalLuminance = 0, count = 0;

        for (let i = 0; i < imageData.length; i += 4 * 10) { // sample every 10 pixels for performance
            r = imageData[i];
            g = imageData[i + 1];
            b = imageData[i + 2];

            // formule de luminance perçue
            const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
            totalLuminance += luminance;
            count++;
        }

        const averageLuminance = totalLuminance / count;
        const isDark = averageLuminance < 128;

        svgIcon.classList.remove('text-white', 'text-black-950');
        svgIcon.classList.add(isDark ? 'text-white' : 'text-black-950');
        };

        /* 🔧 Modale Édition Générique */
        const dynamicModal = document.getElementById('genericEditModal');
        const modalTitle = dynamicModal.querySelector('#genericModalTitle');
        const modalForm = dynamicModal.querySelector('form');
        const fieldContainer = dynamicModal.querySelector('#genericModalField');

        window.openModal = function ({ title, action, fieldName, value }) {
            modalTitle.textContent = title;
            modalForm.action = action;

            // Nettoyer l'ancien champ
            fieldContainer.innerHTML = "";

            if (fieldName === "description") {
                fieldContainer.innerHTML = `
                    <textarea 
                        name="description" 
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

        dynamicModal.addEventListener("click", (e) => {
            const content = dynamicModal.querySelector("div");
            if (!content.contains(e.target)) {
                dynamicModal.classList.add("hidden");
            }
        });



    });
}