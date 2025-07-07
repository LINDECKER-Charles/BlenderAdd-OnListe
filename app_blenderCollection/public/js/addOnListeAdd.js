document.addEventListener("turbo:load", function () {
console.log("bahahaa")
  const input = document.querySelector("#addon_url");
  const preview = document.querySelector("#addon-preview");

  if (!input || !preview) return;

  let typingTimer;
  let controller;
  const delay = 800;

  input.addEventListener("input", () => {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      const url = input.value.trim();
      if (!url.startsWith("http")) {
        preview.innerHTML = "Preview.";
        return;
      }

      if (controller) controller.abort();
      controller = new AbortController();

      fetch(`/api/scrape-addon/0?url=${encodeURIComponent(url)}`, {
        signal: controller.signal,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.error) {
            preview.innerHTML = `<p class="text-red-500">${data.error}</p>`;
            return;
          }
        preview.innerHTML = `
        <div class="w-full h-full overflow-y-auto flex flex-col justify-start gap-2 px-3 py-2 text-sm text-black-950 scrollbar-thin scrollbar-thumb-grey-600 scrollbar-track-grey-800">
            <div class="flex flex-col">
            <p class="font-semibold text-grey-400">Titre :</p>
            <p class="truncate">${data.title}</p>
            </div>

            <div class="flex flex-col">
            <p class="font-semibold text-grey-400">Poids :</p>
            <p>${data.size ?? 'Inconnu'}</p>
            </div>

            <div class="flex flex-col">
            <p class="font-semibold text-grey-400">Tags :</p>
            <div class="flex flex-wrap gap-1 mt-0.5">
                ${(data.tags ?? []).map(tag => `
                <span class="bg-grey-700 text-black-950 text-xs px-2 py-0.5 rounded">
                    ${tag}
                </span>
                `).join('')}
            </div>
            </div>
        </div>
        `;
        })
        .catch((error) => {
          if (error.name !== "AbortError") {
            preview.innerHTML = `<p class="text-red-500">Erreur de chargement</p>`;
          }
        });
    }, delay);
  });
});
