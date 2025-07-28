export default function initModalScript() {
    console.log("Script chartJS1.js chargé ✅");

    const dataScript = document.getElementById('collection-data');
    const rawData = JSON.parse(dataScript.textContent); // ici on récupère ton JSON proprement

    const labels = rawData.map(e => e.date);
    const data = rawData.map(e => parseInt(e.count));

    const ctx = document.getElementById('collectionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Collections créées par jour',
                data: data,
                borderWidth: 2,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: 'white' } },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: 'white' },
                    grid: { color: '#444' }
                },
                x: {
                    ticks: { color: 'white', autoSkip: true, maxTicksLimit: 10 },
                    grid: { color: '#444' }
                }
            }
        }
    });
}