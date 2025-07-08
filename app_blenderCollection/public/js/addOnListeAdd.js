document.addEventListener("turbo:load", function () {
  console.log("bahahaa");
  const input = document.querySelector("#addon_url");
  const preview = document.querySelector("#addon-preview");

  if (!input || !preview) return;

  let typingTimer;
  let controller;
  const delay = 10;

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

      fetch(`/api/scrape-addon?url=${encodeURIComponent(url)}`, {
        signal: controller.signal,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.error) {
            preview.innerHTML = `<p class="text-red-500">${data.error}</p>`;
            return;
          }
          preview.innerHTML = `
            <div class="w-full h-full overflow-y-auto flex flex-row justify-start gap-6 px-3 py-2 text-sm text-black-950 scrollbar-thin scrollbar-thumb-grey-600 scrollbar-track-grey-800">

              <!-- Bloc 1 : Titre + Poids -->
              <div class="flex-1 flex flex-col gap-2">
                <div>
                  <p class="font-semibold text-grey-400">Titre :</p>
                  <p class="truncate">${data.title}</p>
                </div>
                <div>
                  <p class="font-semibold text-grey-400">Poids :</p>
                  <p>${data.size ?? 'Inconnu'}</p>
                </div>
              </div>

              <!-- Bloc 2 : Tags -->
              <div class="flex-1 flex flex-col gap-2">
                <p class="font-semibold text-grey-400">Tags :</p>
                <div class="flex flex-wrap gap-1">
                  ${(data.tags ?? []).map(tag => `
                    <span class=" m-1 p-1 rounded-md text-xs border-[1px] border-grey-500">
                      ${tag}
                    </span>
                  `).join('')}
                </div>
              </div>

              <!-- Bloc 3 : Image -->
              <div class="flex-1 flex flex-col items-center justify-center">
                ${data.image ? `
                  <a href="${url}">
                    <img src="${data.image}" alt="Addon preview" class="w-full h-full object-contain rounded border border-grey-700">
                  </a>
                ` : `
                  <div class="text-grey-500 text-xs">Pas d’image</div>
                `}
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

  const addOnButton = document.getElementById("add_addon");
  const addOnListe = document.getElementById("addOnListe");
  addOnButton.addEventListener("click", () => {
    const url = input.value.trim();
    if (!url.startsWith("http")) {
      preview.innerHTML = "Preview.";
      return;
    }
    const confirmAdd = confirm(`Ajouter cet add-on à la collection ?\n\n${url}`);
    if (!confirmAdd) {
      return;
    }
    fetch(`/api/getAddOnSave?url=${encodeURIComponent(url)}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.empty || data.length === 0) {
          addOnListe.innerHTML = `<p class="text-grey-500 text-sm">Liste des add-ons</p>`;
          return;
        }

        renderAddOnTable(data);
        input.value = "";  
      })
      .catch((error) => {
        if (error.name !== "AbortError") {
          preview.innerHTML = `<p class="text-red-500">Erreur de chargement</p>`;
        }
    });
  });

  function attachDeleteEvents() {
    document.querySelectorAll(".delete-addon").forEach(button => {
      button.addEventListener("click", (e) => {
        const urlToDelete = e.currentTarget.getAttribute("data-url");

        fetch(`/api/suprAddOnSave?url=${encodeURIComponent(urlToDelete)}`)
          .then(res => res.json())
          .then(data => {
            if (data.empty || data.length === 0) {
              addOnListe.innerHTML = `<p class="text-grey-500 text-sm">Liste des add-ons</p>`;
              return;
            }

            // Vérification défensive : s'assurer que data est bien un tableau
            if (!Array.isArray(data)) {
              console.warn("Données inattendues :", data);
              return;
            }

            renderAddOnTable(data); // ↩️ On peut afficher
          });
      });
    });
  }

  function renderAddOnTable(data) {
    addOnListe.innerHTML = `
      <table class="w-full h-full text-sm text-left text-white border border-grey-700 rounded">
        <thead class="bg-grey-800 uppercase text-xs text-grey-400">
          <tr>
            <th scope="col" class="px-4 py-3">#</th>
            <th scope="col" class="px-4 py-3">Add-on URL</th>
            <th scope="col" class="px-4 py-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="bg-black-950 divide-y divide-grey-700">
          ${data.map((addon, index) => `
            <tr>
              <td class="px-4 py-3 text-sm text-center text-grey-400">${index + 1}</td>
              <td class="px-4 py-3 break-all">${addon[0]}</td>
              <td class="px-4 py-3 text-center">
                <a data-url="${addon[0]}" class="delete-addon hover:cursor-pointer bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded">
                  Supprimer
                </a>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    attachDeleteEvents();
  }
    

});
