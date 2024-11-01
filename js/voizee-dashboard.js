jQuery(function ($) {
    const stats = $.parseJSON($(".voizee-dash").attr("data-stats"));
    const categories = $.parseJSON($(".voizee-dash").attr("data-dates")).reverse();
    let data = [];
    let calls = stats && stats.stats ? stats.stats.calls : [];

    $('#voizee_total_calls').text(stats.total_calls || 0);
    $('#voizee_total_unique_calls').text(stats.total_unique_calls || 0);
    $('#voizee_average_call_length').text(stats.average_call_length || 'N/A');
    $('#voizee_top_call_source').text(stats.top_call_source || 'N/A');

    for (let i = 0, len = categories.length; i < len; ++i) {
        data.push(0);
    }
    for (let c in calls) {
        data[categories.indexOf(c)] = calls[c];
    }

    const ctx = document.getElementById('voizee-stat').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: categories,
            datasets: [
                {
                    label: 'Calls',
                    data: data,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                },
            ],
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Calls'
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
