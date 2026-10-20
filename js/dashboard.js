
const menuButton = document.getElementById("menuButton");
const sidebar = document.getElementById("sidebar");

if (menuButton && sidebar) {
    menuButton.addEventListener("click", function () {
        sidebar.classList.toggle("show");
    });
}

const chartElement = document.getElementById("salesChart");
const chartPeriod = document.getElementById("chartPeriod");

if (
    chartElement &&
    chartPeriod &&
    window.dashboardData &&
    typeof Chart !== "undefined"
) {
    const chartContext = chartElement.getContext("2d");

    const gradient = chartContext.createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, "rgba(220, 72, 108, 0.25)");
    gradient.addColorStop(1, "rgba(220, 72, 108, 0.01)");

    function ambilDataGrafik(periode) {
        const data = window.dashboardData[String(periode)] || [];

        return {
            labels: data.map(function (item) {
                return item.label;
            }),

            values: data.map(function (item) {
                return Number(item.total) || 0;
            })
        };
    }

    const dataAwal = ambilDataGrafik(chartPeriod.value);

    const salesChart = new Chart(chartContext, {
        type: "line",

        data: {
            labels: dataAwal.labels,

            datasets: [
                {
                    label: "Penjualan",
                    data: dataAwal.values,

                    borderColor: "#df486c",
                    backgroundColor: gradient,
                    borderWidth: 2,

                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: "#df486c",

                    tension: 0.4,
                    fill: true
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return "Penjualan: Rp " +
                                Number(context.raw).toLocaleString("id-ID");
                        }
                    }
                }
            },

            scales: {
                x: {
                    grid: {
                        display: false
                    },

                    ticks: {
                        font: {
                            size: 9
                        },

                        color: "#aaa"
                    }
                },

                y: {
                    beginAtZero: true,

                    grid: {
                        color: "#f3eeee"
                    },

                    ticks: {
                        font: {
                            size: 9
                        },

                        color: "#aaa",

                        callback: function (value) {
                            return "Rp " +
                                Number(value).toLocaleString("id-ID");
                        }
                    }
                }
            }
        }
    });


    chartPeriod.addEventListener("change", function () {
        const dataBaru = ambilDataGrafik(this.value);

        salesChart.data.labels = dataBaru.labels;
        salesChart.data.datasets[0].data = dataBaru.values;

        salesChart.update();
    });
}
