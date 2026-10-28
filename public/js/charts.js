/**
 * GESTION+ : Initialisation des Graphiques Chart.js (Dashboard Dynamique)
 * Style corporate soigné, prêt pour les données réelles
 */

let requestsChartInstance = null;
let articlesDonutInstance = null;

function initDashboardCharts(data = null) {
  // 1. Graphique : Évolution des demandes
  const ctxRequests = document.getElementById('requestsEvolutionChart');
  if (ctxRequests) {
    if (requestsChartInstance) {
      requestsChartInstance.destroy();
    }

    const labels = (data && data.evolutionLabels) ? data.evolutionLabels : ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin'];
    const approvedData = (data && data.approved) ? data.approved : [0, 0, 0, 0, 0, 0];
    const pendingData = (data && data.pending) ? data.pending : [0, 0, 0, 0, 0, 0];
    const rejectedData = (data && data.rejected) ? data.rejected : [0, 0, 0, 0, 0, 0];

    requestsChartInstance = new Chart(ctxRequests, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Approuvées',
            data: approvedData,
            backgroundColor: '#2563eb', // Bleu corporate
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.65
          },
          {
            label: 'En attente',
            data: pendingData,
            backgroundColor: '#0d9488', // Teal
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.65
          },
          {
            label: 'Rejetées',
            data: rejectedData,
            backgroundColor: '#f87171', // Rouge doux
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.65
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: {
              boxWidth: 12,
              boxHeight: 12,
              usePointStyle: true,
              pointStyle: 'circle',
              font: {
                family: "'Plus Jakarta Sans', sans-serif",
                size: 12,
                weight: '500'
              },
              color: '#64748b',
              padding: 16
            }
          },
          tooltip: {
            backgroundColor: '#0f172a',
            titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 13, weight: '600' },
            bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
            padding: 10,
            cornerRadius: 8,
            boxPadding: 4
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              font: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
              color: '#94a3b8'
            }
          },
          y: {
            border: { dash: [4, 4] },
            grid: { color: '#f1f5f9' },
            ticks: {
              stepSize: 5,
              font: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
              color: '#94a3b8'
            },
            beginAtZero: true
          }
        }
      }
    });
  }

  // 2. Graphique : Répartition des articles (Donut avec texte central)
  const ctxDonut = document.getElementById('articlesDistributionChart');
  if (ctxDonut) {
    if (articlesDonutInstance) {
      articlesDonutInstance.destroy();
    }

    let catLabels = ['Papeterie', 'Informatique', 'Fournitures', 'Gadgets', 'Autres'];
    let catValues = [0, 0, 0, 0, 0];
    let totalArticles = 0;

    if (data && data.categories && data.categories.length > 0) {
      catLabels = data.categories.map(c => c.category);
      catValues = data.categories.map(c => Number(c.count || c.total_qty || 0));
      totalArticles = catValues.reduce((a, b) => a + b, 0);
    } else if (data && typeof data.totalArticles === 'number') {
      totalArticles = data.totalArticles;
    }

    const centerTextPlugin = {
      id: 'centerText',
      beforeDraw(chart) {
        const { width, height } = chart;
        const ctx = chart.ctx;
        ctx.restore();

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#0f172a';

        const fontSizeNumber = Math.min(height, width) / 114;
        ctx.font = `700 ${Math.round(fontSizeNumber * 12)}px 'Plus Jakarta Sans', sans-serif`;
        ctx.fillText(`${totalArticles}`, width / 2, height / 2 - 8);

        ctx.font = `500 ${Math.round(fontSizeNumber * 6.5)}px 'Plus Jakarta Sans', sans-serif`;
        ctx.fillStyle = '#64748b';
        ctx.fillText(totalArticles <= 1 ? 'article' : 'articles', width / 2, height / 2 + 13);

        ctx.save();
      }
    };

    const displayData = totalArticles === 0 ? [1] : catValues;
    const displayColors = totalArticles === 0 
      ? ['#e2e8f0'] 
      : ['#2563eb', '#0d9488', '#f59e0b', '#8b5cf6', '#94a3b8'];

    articlesDonutInstance = new Chart(ctxDonut, {
      type: 'doughnut',
      data: {
        labels: totalArticles === 0 ? ['Aucun article'] : catLabels,
        datasets: [
          {
            data: displayData,
            backgroundColor: displayColors,
            borderWidth: 3,
            borderColor: '#ffffff',
            hoverOffset: totalArticles === 0 ? 0 : 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: totalArticles > 0,
            backgroundColor: '#0f172a',
            callbacks: {
              label: function(context) {
                return ` ${context.label} : ${context.raw}`;
              }
            }
          }
        }
      },
      plugins: [centerTextPlugin]
    });

    // Mettre à jour dynamiquement la liste de légende à droite du donut
    const legendList = document.getElementById('categoriesLegendList');
    if (legendList) {
      if (totalArticles === 0 || !data || !data.categories || data.categories.length === 0) {
        legendList.innerHTML = '<li class="p-3 text-center text-muted small bg-light rounded"><i class="bi bi-pie-chart me-1"></i> Aucun article enregistré en stock.</li>';
      } else {
        const colors = ['#2563eb', '#0d9488', '#f59e0b', '#8b5cf6', '#94a3b8'];
        legendList.innerHTML = '';
        data.categories.forEach((cat, idx) => {
          const color = colors[idx % colors.length];
          const val = Number(cat.count || cat.total_qty || 0);
          const pct = totalArticles > 0 ? Math.round((val / totalArticles) * 100) : 0;
          const li = document.createElement('li');
          li.className = 'd-flex align-items-center justify-content-between p-1 px-2 rounded';
          li.style.background = '#f8fafc';
          li.style.border = '1px solid #f1f5f9';
          li.innerHTML = `
            <span class="d-flex align-items-center gap-2">
              <span style="width: 10px; height: 10px; background: ${color}; border-radius: 50%; flex-shrink: 0;"></span>
              <span class="text-secondary fw-medium">${cat.category}</span>
            </span>
            <strong class="text-dark">${pct}% (${val})</strong>
          `;
          legendList.appendChild(li);
        });
      }
    }
  }
}

// Initialisation globale
window.addEventListener('DOMContentLoaded', () => {
  initDashboardCharts();
});
