export default function initAddonScript() {
    const desc = document.getElementById('descriptionText');
    const btn = document.getElementById('toggleDescription');

    // Utilitaire : détecte si le texte est tronqué
    const isTruncated = () => desc.scrollHeight > desc.clientHeight;

    if (isTruncated()) {
      btn.classList.remove('hidden');
    }

    let expanded = false;
    btn.addEventListener('click', () => {
      expanded = !expanded;
      desc.classList.toggle('line-clamp-3');
      btn.textContent = expanded ? 'Voir moins' : 'Voir plus';
    });
}