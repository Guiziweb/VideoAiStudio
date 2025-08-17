import ApexCharts from 'apexcharts';

let chart = null;

function renderWalletChart() {
    const chartElement = document.querySelector('#wallet-transactions-chart');
    
    if (!chartElement) {
        return;
    }

    // Vérifier que les données existent
    if (!chartElement.dataset.balances) {
        console.error('Balances data not found');
        return;
    }

    const options = {
        colors: ['#206bc4'],
        series: [{
            name: 'Solde du wallet',
            data: JSON.parse(chartElement.dataset.balances)
        }],
        chart: {
            toolbar: {
                show: false
            },
            height: 350,
            type: 'line'
        },
        xaxis: {
            categories: JSON.parse(chartElement.dataset.intervals),
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            labels: {
                formatter(val) {
                    return `${val} tokens`;
                }
            }
        },
        stroke: {
            curve: 'smooth'
        }
    };

    chart = new ApexCharts(chartElement, options);
    chart.render();
}

renderWalletChart();

// Observer pour rechargement automatique lors de Live Components
const element = document.querySelector('#wallet-transactions-chart');
if (element) {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-balances' || mutation.attributeName === 'data-intervals') {
                if (chart) {
                    chart.destroy();
                }
                renderWalletChart();
            }
        });
    });

    observer.observe(element, {
        attributes: true
    });
}