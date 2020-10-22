function drawChart(uid, chartConfig) {

	const ctx = document.getElementById('chart-' + uid).getContext('2d');

	new Chart(ctx, chartConfig);

}

const bwcovidnumbers = window.bwcovidnumbers;

if (bwcovidnumbers && typeof bwcovidnumbers === 'object') {
	for (const [key, value] of Object.entries(bwcovidnumbers)) {
		drawChart(key.substr(1), value);
	}
}
