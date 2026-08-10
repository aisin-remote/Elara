import Chart from 'chart.js/auto';

const palette = {
    orbit: '#2eb0fb',
    emerald: '#10b981',
    rose: '#fb7185',
    violet: '#8b5cf6',
    grid: 'rgba(148, 163, 184, .18)',
    text: '#64748b',
};

function sourceFor(canvas) {
    const source = document.getElementById(canvas.dataset.chartSource);

    return source ? JSON.parse(source.textContent) : null;
}

function scales({ stacked = false, horizontal = false } = {}) {
    const axis = {
        stacked,
        beginAtZero: true,
        grid: { color: palette.grid },
        ticks: { color: palette.text, precision: 0, font: { size: 10 } },
        border: { display: false },
    };

    return horizontal
        ? { x: axis, y: { ...axis, grid: { display: false } } }
        : { x: { ...axis, grid: { display: false } }, y: axis };
}

function options(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { padding: 10, cornerRadius: 8 },
        },
        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 350 },
        ...extra,
    };
}

function overduePattern() {
    const tile = document.createElement('canvas');
    tile.width = 10;
    tile.height = 10;
    const context = tile.getContext('2d');
    const dark = document.documentElement.classList.contains('dark');

    context.fillStyle = dark ? '#3b1d2b' : '#fff1f2';
    context.fillRect(0, 0, tile.width, tile.height);
    context.strokeStyle = palette.rose;
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(-2, 10);
    context.lineTo(10, -2);
    context.moveTo(3, 13);
    context.lineTo(13, 3);
    context.stroke();

    return context.createPattern(tile, 'repeat');
}

function performanceConfig(data) {
    const dark = document.documentElement.classList.contains('dark');
    const tooltipOrder = { Complete: 0, 'New Task': 1, Overdue: 2 };
    const dataset = (label, values, backgroundColor) => ({
        label,
        data: values,
        backgroundColor,
        borderColor: backgroundColor,
        borderSkipped: false,
        borderRadius: 9,
        borderWidth: 0,
        barPercentage: .78,
        categoryPercentage: .72,
        maxBarThickness: 58,
        stack: 'tasks',
    });

    return {
        type: 'bar',
        data: {
            labels: data.labels.map((label) => label.toUpperCase()),
            datasets: [
                dataset('Overdue', data.overdue, overduePattern()),
                dataset('New Task', data.created, dark ? '#334155' : '#f1f5f9'),
                dataset('Complete', data.completed, palette.emerald),
            ],
        },
        options: options({
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 2 } },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: palette.text,
                        font: { size: 11, weight: 500 },
                        maxRotation: 0,
                        callback(value) {
                            const label = this.getLabelForValue(value);

                            return (data.unit === 'month' ? label.split(' ')[0] : label).toUpperCase();
                        },
                    },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: palette.grid, borderDash: [6, 6], drawTicks: false },
                    ticks: { color: palette.text, precision: 0, maxTicksLimit: 6, padding: 10, font: { size: 10 } },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: dark ? '#0f172a' : '#ffffff',
                    bodyColor: dark ? '#cbd5e1' : '#64748b',
                    borderColor: dark ? '#334155' : '#e2e8f0',
                    borderWidth: 1,
                    boxHeight: 7,
                    boxWidth: 7,
                    cornerRadius: 10,
                    displayColors: true,
                    padding: 12,
                    titleColor: dark ? '#f8fafc' : '#0f172a',
                    usePointStyle: true,
                    itemSort: (a, b) => tooltipOrder[a.dataset.label] - tooltipOrder[b.dataset.label],
                    callbacks: {
                        title(items) {
                            const label = items[0]?.label ?? '';
                            if (data.unit !== 'month') return label;

                            const date = new Date(`${label.split(' ')[0]} 1, 2000`);

                            return new Intl.DateTimeFormat('en', { month: 'long' }).format(date);
                        },
                    },
                },
            },
        }),
    };
}

function trendConfig(data, type) {
    if (type === 'completion') {
        return {
            type: 'line',
            data: { labels: data.labels, datasets: [{ label: 'Completed', data: data.completed, borderColor: palette.emerald, backgroundColor: 'rgba(16, 185, 129, .12)', fill: true, tension: .35, pointRadius: 2 }] },
            options: options({ scales: scales() }),
        };
    }

    if (type === 'total-completed') {
        return {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: 'Created', data: data.created, backgroundColor: palette.orbit, borderRadius: 6 }, { label: 'Completed', data: data.completed, backgroundColor: palette.emerald, borderRadius: 6 }] },
            options: options({ scales: scales() }),
        };
    }

    return performanceConfig(data);
}

function distributionConfig(data) {
    return {
        type: 'doughnut',
        data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: data.colors, borderWidth: 0, hoverOffset: 4 }] },
        options: options({ cutout: '68%', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, color: palette.text, padding: 14, font: { size: 10 } } } } }),
    };
}

function workloadConfig(data) {
    return {
        type: 'bar',
        data: { labels: data.map((member) => member.name), datasets: [{ label: 'Open', data: data.map((member) => member.open), backgroundColor: palette.violet, borderRadius: 6 }, { label: 'Completed', data: data.map((member) => member.completed), backgroundColor: palette.emerald, borderRadius: 6 }] },
        options: options({ indexAxis: 'y', scales: scales({ horizontal: true }), plugins: { legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, color: palette.text } } } }),
    };
}

export function initCharts() {
    document.querySelectorAll('[data-orbitra-chart]').forEach((canvas) => {
        const data = sourceFor(canvas);
        if (!data) return;

        const type = canvas.dataset.orbitraChart;
        const config = type === 'distribution'
            ? distributionConfig(data)
            : type === 'workload'
                ? workloadConfig(data)
                : trendConfig(data, type);

        new Chart(canvas, config);
    });
}
