(() => {
    const form = document.getElementById('periodForm');
    const periodSelect = document.getElementById('periodSelect');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const menuButton = document.getElementById('mobileMenuButton');
    const collapseButton = document.getElementById('desktopCollapseButton');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebarScrim');
    const appShell = document.querySelector('.app-shell');

    if (collapseButton && appShell) {
        collapseButton.addEventListener('click', () => {
            const isExpanded = appShell.classList.toggle('is-sidebar-collapsed') === false;
            collapseButton.setAttribute('aria-expanded', String(isExpanded));
            collapseButton.setAttribute('aria-label', isExpanded ? 'Réduire la barre latérale' : 'Développer la barre latérale');
            collapseButton.title = isExpanded ? 'Réduire la barre latérale' : 'Développer la barre latérale';
        });
    }

    if (form && periodSelect && startDate && endDate) {
        const syncDateFields = () => {
            const customRange = periodSelect.value === 'custom';
            startDate.disabled = !customRange;
            endDate.disabled = !customRange;
            startDate.required = customRange;
            endDate.required = customRange;
        };

        const toInputDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        periodSelect.addEventListener('change', () => {
            syncDateFields();

            if (periodSelect.value !== 'custom') {
                const end = new Date();
                const start = new Date(end);
                start.setDate(start.getDate() - Number(periodSelect.value) + 1);
                startDate.value = toInputDate(start);
                endDate.value = toInputDate(end);
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Mise à jour…';
            }
        });

        syncDateFields();
    }

    const closeMenu = () => {
        if (!sidebar || !menuButton || !scrim) {
            return;
        }

        sidebar.classList.remove('is-open');
        scrim.classList.remove('is-visible');
        menuButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
    };

    if (menuButton && sidebar && scrim) {
        menuButton.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('is-open');
            scrim.classList.toggle('is-visible', isOpen);
            menuButton.setAttribute('aria-expanded', String(isOpen));
            document.body.classList.toggle('menu-open', isOpen);
        });

        scrim.addEventListener('click', closeMenu);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }

    const dataNode = document.getElementById('dashboardData');
    if (!dataNode) {
        return;
    }

    const showChartError = (canvas) => {
        const container = canvas?.closest('.panel');
        const message = container?.querySelector('.chart-load-error');
        const loading = container?.querySelector('.chart-loading');

        if (message) {
            message.hidden = false;
        }

        if (loading) {
            loading.hidden = true;
        }

        if (canvas) {
            canvas.hidden = true;
        }
    };

    let data;
    try {
        data = JSON.parse(dataNode.textContent || '{}');
    } catch {
        document.querySelectorAll('canvas').forEach(showChartError);
        return;
    }

    const chartColors = ['#28765b', '#74a88c', '#c5a054', '#d47f78', '#94a99c', '#539789', '#869bd1', '#d99e55', '#9a82b6'];
    const formatCurrency = (value) => new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'BIF',
        maximumFractionDigits: 0,
    }).format(value);

    const dateLabel = (isoDate, granularity) => {
        if (granularity === 'monthly') {
            const [year, month] = isoDate.split('-').map(Number);
            return new Intl.DateTimeFormat('fr-FR', { month: 'short', year: '2-digit' })
                .format(new Date(year, month - 1, 1));
        }

        const [year, month, day] = isoDate.split('-').map(Number);
        const options = granularity === 'weekly'
            ? { day: 'numeric', month: 'short' }
            : { day: 'numeric', month: 'short' };
        return new Intl.DateTimeFormat('fr-FR', options).format(new Date(year, month - 1, day));
    };

    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#17392f',
                padding: 11,
                cornerRadius: 8,
                titleFont: { family: 'DM Sans', weight: '600' },
                bodyFont: { family: 'DM Sans' },
                displayColors: false,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: {
                    color: '#7c8b83',
                    font: { family: 'DM Sans', size: 10 },
                    maxTicksLimit: 8,
                    maxRotation: 0,
                    autoSkip: true,
                },
            },
            y: {
                beginAtZero: true,
                border: { display: false, dash: [3, 4] },
                grid: { color: '#edf1ee', drawTicks: false },
                ticks: {
                    color: '#7c8b83',
                    font: { family: 'DM Sans', size: 10 },
                    padding: 9,
                    precision: 0,
                },
            },
        },
    };

    const charts = [];

    if (typeof window.Chart !== 'function') {
        document.querySelectorAll('canvas').forEach(showChartError);
        return;
    }

    const granularity = new URLSearchParams(window.location.search).get('granularity') || 'daily';
    const labels = (data.labels || []).map((label) => dateLabel(label, granularity));
    const salesCanvas = document.getElementById('salesChart');
    const revenueCanvas = document.getElementById('revenueChart');

    if (salesCanvas && (data.sales || []).length) {
        try {
            charts.push(new window.Chart(salesCanvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data: data.sales,
                        backgroundColor: '#d8ebe0',
                        hoverBackgroundColor: '#28765b',
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 24,
                    }],
                },
                options: {
                    ...chartDefaults,
                    scales: {
                        ...chartDefaults.scales,
                        y: {
                            ...chartDefaults.scales.y,
                            suggestedMax: Math.max(3, ...data.sales),
                            ticks: { ...chartDefaults.scales.y.ticks, stepSize: 1 },
                        },
                    },
                    plugins: {
                        ...chartDefaults.plugins,
                        tooltip: {
                            ...chartDefaults.plugins.tooltip,
                            callbacks: {
                                title: (items) => items[0]?.label || '',
                                label: (item) => `${item.parsed.y} vente${item.parsed.y > 1 ? 's' : ''}`,
                            },
                        },
                    },
                },
            }));
        } catch {
            showChartError(salesCanvas);
        }
    }

    if (revenueCanvas && (data.revenue || []).length) {
        try {
            charts.push(new window.Chart(revenueCanvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: data.revenue,
                        borderColor: '#28765b',
                        backgroundColor: 'rgba(40, 118, 91, .10)',
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#28765b',
                        fill: true,
                        tension: 0.36,
                    }],
                },
                options: {
                    ...chartDefaults,
                    scales: {
                        ...chartDefaults.scales,
                        y: {
                            ...chartDefaults.scales.y,
                            ticks: {
                                ...chartDefaults.scales.y.ticks,
                                callback: (value) => formatCurrency(value),
                            },
                        },
                    },
                    plugins: {
                        ...chartDefaults.plugins,
                        tooltip: {
                            ...chartDefaults.plugins.tooltip,
                            callbacks: {
                                title: (items) => items[0]?.label || '',
                                label: (item) => formatCurrency(item.parsed.y),
                            },
                        },
                    },
                },
            }));
        } catch {
            showChartError(revenueCanvas);
        }
    }

    const categoryCanvas = document.getElementById('categoryChart');
    if (categoryCanvas && (data.categories || []).length) {
        try {
            charts.push(new window.Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: data.categories.map((category) => category.label),
                    datasets: [{
                        data: data.categories.map((category) => category.count),
                        backgroundColor: data.categories.map((_, index) => chartColors[index % chartColors.length]),
                        borderColor: '#fff',
                        borderWidth: 3,
                        hoverOffset: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '73%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#17392f',
                            padding: 11,
                            cornerRadius: 8,
                            callbacks: {
                                label: (item) => {
                                    const total = item.dataset.data.reduce((sum, value) => sum + value, 0);
                                    const percentage = total ? Math.round((item.parsed / total) * 100) : 0;
                                    return `${item.label} : ${item.parsed} (${percentage} %)`;
                                },
                            },
                        },
                    },
                },
            }));
        } catch {
            showChartError(categoryCanvas);
        }

        document.querySelectorAll('#categoryLegend .legend-dot').forEach((dot) => {
            const index = Number(dot.dataset.index);
            dot.style.backgroundColor = chartColors[index % chartColors.length];
        });
    }

    if (charts.length > 0) {
        document.querySelectorAll('.chart-loading').forEach((message) => {
            message.hidden = true;
        });
    }
})();
