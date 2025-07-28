    export default function initModalScript() {
    console.log("Script chartJS2.js chargé ✅");

    const rawHourlyData = JSON.parse(document.getElementById('collection-hourly-data').textContent);
    const select = document.getElementById('day-select');
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');

    // Grouper les dates uniques pour le select
    const availableDays = [...new Set(rawHourlyData.map(e => e.date.slice(0, 10)))];
    availableDays.forEach(date => {
        const option = document.createElement('option');
        option.value = date;
        option.textContent = date;
        select.appendChild(option);
    });

    let hourlyChart;

    // Générer le graphique par heure
    function renderHourlyChart(day) {
        const filtered = rawHourlyData.filter(e => e.date.startsWith(day));
        const hoursCount = Array(24).fill(0);

        filtered.forEach(e => {
            const hour = parseInt(e.date.slice(11, 13));
            hoursCount[hour]++;
        });

        const labels = Array.from({ length: 24 }, (_, i) => `${i.toString().padStart(2, '0')}:00`);

        if (hourlyChart) hourlyChart.destroy();

        hourlyChart = new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: `Collections créées le ${day}`,
                    data: hoursCount,
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: 'white' } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: 'white' },
                        grid: { color: '#444' }
                    },
                    x: {
                        ticks: { color: 'white' },
                        grid: { color: '#444' }
                    }
                }
            }
        });
    }

    // Initialiser avec le 1er jour
    renderHourlyChart(availableDays[0]);
    select.addEventListener('change', (e) => renderHourlyChart(e.target.value));
}
    

