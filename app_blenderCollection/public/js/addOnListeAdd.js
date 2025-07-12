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

    /* const confirmAdd = confirm(`Ajouter cet add-on à la collection ?\n\n${url}`);
    if (!confirmAdd) return; */

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
}
