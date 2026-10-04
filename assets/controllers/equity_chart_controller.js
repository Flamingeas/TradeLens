import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

/*
 * Courbe de capital : P&L net cumulé, trade après trade.
 * Les couleurs viennent des variables CSS (--chart-*), définies dans app.css pour le thème sombre.
 */
export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        labels: Array,
        series: Array,
    };

    connect() {
        const money = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'USD',
            currencyDisplay: 'narrowSymbol',
            signDisplay: 'exceptZero',
        });
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels: this.labelsValue,
                datasets: [
                    {
                        label: 'P&L net cumulé',
                        data: this.seriesValue,
                        borderWidth: 2,
                        pointRadius: this.seriesValue.length > 60 ? 0 : 3,
                        pointHoverRadius: 5,
                        tension: 0.15,
                        fill: 'origin',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reducedMotion ? false : undefined,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (item) => money.format(item.parsed.y) } },
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { ticks: { callback: (value) => money.format(value) } },
                },
            },
        });

        this.applyTheme();
    }

    disconnect() {
        this.chart?.destroy();
    }

    applyTheme() {
        const style = getComputedStyle(this.element);
        const color = (name) => style.getPropertyValue(name).trim();
        const { chart } = this;

        chart.data.datasets[0].borderColor = color('--chart-line');
        chart.data.datasets[0].backgroundColor = color('--chart-fill');
        chart.data.datasets[0].pointBackgroundColor = color('--chart-line');
        chart.options.scales.x.ticks.color = color('--chart-text');
        chart.options.scales.y.ticks.color = color('--chart-text');
        chart.options.scales.x.grid = { color: color('--chart-grid') };
        chart.options.scales.y.grid = { color: color('--chart-grid') };
        chart.update('none');
    }
}
