var ctx = document.getElementById('myChart').getContext('2d');


var mixedChart = new Chart(ctx, {
	type: 'bar',
	data: {
		datasets: [{
			data: window.bwcovidnumbers.dataset1data,
			label: window.bwcovidnumbers.dataset1label,
			backgroundColor: 'rgba(108,190,191,0.1)',
			borderColor: 'rgba(108,190,191,0.9)',
			order: 2,
			borderWidth: 1
		}, {
			data: window.bwcovidnumbers.dataset2data,
			label: window.bwcovidnumbers.dataset2label,
			type: 'line',
			backgroundColor: 'rgba(242,163,84,0)',
			borderColor: 'rgba(242,163,84,1)',
			order: 1,
			borderWidth: 1
		}],
		labels: window.bwcovidnumbers.labels,

	},
	options: {
		color: false
	}

});
