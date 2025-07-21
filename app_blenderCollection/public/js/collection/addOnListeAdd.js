export default function initAddonScript() {
  console.log("Script addOnListeAdd.js chargé ✅");
  const input = document.querySelector("#addon_url");
  const preview = document.querySelector("#addon-preview");

  const addOnButton = document.getElementById("add_addon");
  const addOnListe = document.getElementById("addOnListe");
  addOnButton.addEventListener("click", () => {
    const url = input.value.trim();

    if (!(url.startsWith("http") || url.startsWith("https"))) {
      preview.innerHTML = `<p class="text-red-500 text-sm">URL invalide</p>`;
      return;
    }

    fetch("/api/add-addon", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({ url }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.error) {
          preview.innerHTML = `<p class="text-red-500">${data.error}</p>`;
          return;
        }

        // On récupère la nouvelle liste à jour
        fetch("/api/get-session-addons")
          .then((res) => res.json())
          .then((data) => {
            if (data.empty || data.length === 0) {
              addOnListe.innerHTML = `<p class="text-grey-500 text-sm">Liste des add-ons</p>`;
            } else {
              renderAddOnTable(data);
            }
          });

        input.value = "";
      })
      .catch((error) => {
        console.error(error);
        preview.innerHTML = `<p class="text-red-500">Erreur réseau</p>`;
      });
  fetch("/api/get-session-addons")
    .then((res) => res.json())
    .then((data) => {
      if (data.empty || data.length === 0) {
        addOnListe.innerHTML = `<p class="text-grey-500 text-sm">Liste des add-ons</p>`;
        preview.innerHTML = `<p class="text-grey-500 text-sm">Preview.</p>`;
      } else {
        fetch("/api/addon", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({ url: url }),
        })
          .then(res => {
            console.log("Response status:", res.status);
            return res.json();
          })
          .then(data => {
            console.log("Response JSON:", data);
            renderAddonPreview(data);
          })
          .catch(err => {
            console.error("Erreur JSON ou réseau :", err);
            preview.innerHTML = `<p class="text-red-500 text-sm">Erreur lors du chargement de la preview</p>`;
          });
      }
    });

  });

function renderAddonPreview(addon) {
  preview.innerHTML = `
    <div class="flex items-center gap-4 w-full bg-[#30353B] rounded-xl p-3 border border-[#1B1C1C] shadow">
      <img src="${addon.image || '/img/exemple/ex3.webp'}"
          alt="${addon.title}"
          class="h-20 w-20 rounded-lg object-cover border-2 border-white/20 shadow" />
      
      <div class="flex flex-col justify-center text-[#F3F6F7]">
        <p class="text-sm font-bold leading-snug">${addon.title}</p>
        <p class="text-xs text-[#888C96] mt-1">${addon.tags?.join(', ') || 'Aucun tag'}</p>
        ${addon.size ? `<p class="text-xs text-[#BDC1C7] mt-1">~ ${addon.size}</p>` : ''}
      </div>
    </div>
  `;
}


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
      <table class="min-w-full text-sm text-left border text-black-950 border-[#1B1C1C] rounded overflow-hidden">
        <thead class="bg-[#25282D] text-xs uppercase text-[#888C96]">
          <tr>
            <th class="px-4 py-2 font-semibold">#</th>
            <th class="px-4 py-2 font-semibold">Add-on URL</th>
            <th class="px-4 py-2 text-center font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="bg-[#30353B] divide-y divide-[#1B1C1C]">
          ${data.map((addon, index) => `
            <tr class="hover:bg-[#25282D] transition-colors duration-200">
              <td class="px-4 py-2 text-center text-[#888C96]">${index + 1}</td>
              <td class="px-4 py-2 text-[#BDC1C7] break-words max-w-[200px]">${addon[0]}</td>
              <td class="px-4 py-2 text-center">
                <button 
                  data-url="${addon[0]}"
                  class="delete-addon bg-red-600 hover:bg-red-700 text-[#F3F6F7] text-xs font-semibold px-3 py-1 rounded shadow-sm transition">
                  Supprimer
                </button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    attachDeleteEvents();
  }

}
