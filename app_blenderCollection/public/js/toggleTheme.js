export default function initAddonScript() {
    const toggle = document.getElementById('theme-toggle');
    if (!toggle || toggle.dataset.bound === "true") return;
    const html = document.documentElement;
    toggle.dataset.bound = "true";
    toggle.addEventListener('click', () => {
        html.classList.toggle("dark");
        localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    });
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
} 
 