export default function initAddonScript() {
  console.log("Script addOnListeAdd.js chargé ✅");

  const input = document.querySelector("#addon_url");
  const preview = document.querySelector("#addon-preview");
  const addOnButton = document.getElementById("add_addon");
  const addOnListe = document.getElementById("addOnListe");

  addOnButton.addEventListener("click", async () => {
    const url = input.value.trim();

    if (!(url.startsWith("http") || url.startsWith("https"))) {
      preview.innerHTML = `<p class="text-red-500 text-sm">URL invalide</p>`;
      return;
    }

    try {
      const res = await fetch("/api/add-addon", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({ url }),
      });

      const data = await res.json();

      if (data.error) {
        preview.innerHTML = `<p class="text-red-500">${escapeHTML(data.error)}</p>`;
        return;
      }

      const [addonsRes, addonDataRes] = await Promise.all([
        fetch("/api/get-session-addons"),
        fetch("/api/addon", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({ url }),
        }),
      ]);

      const addons = await addonsRes.json();
      const addonData = await addonDataRes.json();

      if (addons.empty || addons.length === 0) {
        addOnListe.innerHTML = `<p class="text-grey-500 text-sm">Liste des add-ons</p>`;
        preview.innerHTML = `<p class="text-grey-500 text-sm">Preview.</p>`;
      } else {
        renderAddOnTable(addons);
        renderAddonPreview(addonData);
      }

      input.value = "";
    } catch (error) {
      console.error("Erreur réseau ou JSON :", error);
      preview.innerHTML = `<p class="text-red-500 text-sm">Erreur réseau</p>`;
    }
  });

  function renderAddonPreview(addon) {
    preview.innerHTML = `
      <div class="flex items-center gap-4 w-full bg-[#30353B] rounded-xl p-3 border border-[#1B1C1C] shadow h-full">
        <img src="${escapeHTML(addon.image) || '/img/exemple/ex3.webp'}"
             alt="${escapeHTML(addon.title)}"
             class="h-20 w-20 rounded-lg object-cover border-2 border-white/20 shadow" />
        <div class="flex flex-col justify-center text-[#F3F6F7]">
          <p class="text-sm font-bold leading-snug">${escapeHTML(addon.title)}</p>
          <p class="text-xs text-[#888C96] mt-1">${escapeHTML(addon.tags?.join(', ')) || 'Aucun tag'}</p>
          ${addon.size ? `<p class="text-xs text-[#BDC1C7] mt-1">~ ${addon.size}</p>` : ''}
        </div>
      </div>
    `;
  }

  function attachDeleteEvents() {
    document.querySelectorAll(".delete-addon").forEach(button => {
      button.addEventListener("click", async (e) => {
        const urlToDelete = e.currentTarget.getAttribute("data-url");

        try {
          const res = await fetch(`/api/suprAddOnSave?url=${encodeURIComponent(urlToDelete)}`);
          const data = await res.json();

          if (data.empty || data.length === 0) {
            addOnListe.innerHTML = `<p class="text-grey-500 text-sm">Liste des add-ons</p>`;
            return;
          }

          if (!Array.isArray(data)) {
            console.warn("Données inattendues :", data);
            return;
          }

          renderAddOnTable(data);
        } catch (err) {
          console.error("Erreur suppression add-on :", err);
        }
      });
    });
  }

  function renderAddOnTable(data) {
    addOnListe.innerHTML = `
      <table class="w-full text-sm text-left border text-black-950 border-[#1B1C1C] rounded overflow-hidden">
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
              <td class="px-4 py-2 text-[#BDC1C7] break-words max-w-[200px]">${escapeHTML(addon[0])}</td>
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

  function escapeHTML(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}
