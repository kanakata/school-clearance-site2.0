const ctx = document.getElementById('myChart');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C', 'D+', 'D', 'D-' ],
      datasets: [{
        label: 'KCSE PERFORMANCE LAST YEAR',
        data: [2, 9, 31, 40, 2, 3, 9, 31, 40, 2, 3],
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });