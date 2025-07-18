
export default function initModalScript() {

        const avatarImg = document.querySelector('#upload-avatar-form img');
        const svgIcon = document.querySelector('#upload-avatar-form svg');

        const domElements = {
            avatarImg: '#upload-avatar-form img',
            svgIcon: '#upload-avatar-form svg'
        };

        const refs = {};
        let hasError = false;

        for (const [key, selector] of Object.entries(domElements)) {
            const el = document.querySelector(selector);
            if (!el) {
                console.error(`❌ Élément manquant : ${selector}`);
                hasError = true;
            } else {
                refs[key] = el;
            }
        }

        if (hasError) {
            console.error("💥 Le script est interrompu à cause d’éléments DOM manquants.");
            return;
        } else console.log("Script profilImgLum.js chargé ✅");


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
        
}
