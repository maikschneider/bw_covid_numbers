function drawChart(uid, chartConfig) {

	const ctx = document.getElementById('chart-' + uid).getContext('2d');

	new Chart(ctx, {
		type: 'bar',
		data: {
			datasets: [{
				data: chartConfig.dataset1data,
				label: chartConfig.dataset1label,
				backgroundColor: 'rgba(108,190,191,0.1)',
				borderColor: 'rgba(108,190,191,0.9)',
				order: 2,
				borderWidth: 1,
				type: 'bar'
			}, {
				data: chartConfig.dataset2data,
				label: chartConfig.dataset2label,
				type: 'line',
				backgroundColor: 'rgba(242,163,84,0)',
				borderColor: 'rgba(242,163,84,1)',
				order: 1,
				borderWidth: 1
			}],
			labels: chartConfig.labels,

		},
		options: {
			color: false
		}

	});

}

const bwcovidnumbers = window.bwcovidnumbers;

if (bwcovidnumbers && typeof bwcovidnumbers === 'object') {
	for (const [key, value] of Object.entries(bwcovidnumbers)) {
		drawChart(key.substr(1), value);
	}
}
