// js/admin.js
jQuery(document).ready(function ($) {
    // Toggle settings visibility based on email type
    function toggleEmailSettings() {
        var emailType = $('#email_type').val();
        if (emailType === 'html') {
            $('#default_template').closest('tr').show();
        } else {
            $('#default_template').closest('tr').hide();
        }
    }

    $('#email_type').on('change', toggleEmailSettings);
    toggleEmailSettings();

    // Test email form validation
    $('form[action="admin-post.php"]').on('submit', function (e) {
        var email = $(this).find('input[name="test_email"]').val();
        var subject = $(this).find('input[name="test_subject"]').val();
        var message = $(this).find('textarea[name="test_message"]').val();

        if (!email || !subject || !message) {
            e.preventDefault();
            alert('Please fill in all fields.');
            return false;
        }
    });

    // Add status color classes to log entries
    $('.wp-list-table tr').each(function () {
        var status = $(this).find('td:nth-child(4)').text().trim();
        $(this).find('td:nth-child(4)').addClass('status-' + status.toLowerCase());
    });

    // Settings form validation
    $('form').on('submit', function (e) {
        var apiKey = $('#api_key').val();
        var senderEmail = $('#sender_email').val();
        var rateLimit = $('#rate_limit').val();

        if ($('#enable_logging').is(':checked')) {
            if (!confirm('Enabling logging will store all email content in your database. Are you sure you want to proceed?')) {
                e.preventDefault();
                return false;
            }
        }

        if (!apiKey) {
            alert('API Key is required');
            e.preventDefault();
            return false;
        }

        if (!senderEmail) {
            alert('Sender Email is required');
            e.preventDefault();
            return false;
        }

        if (rateLimit && parseInt(rateLimit) < 1) {
            alert('Rate limit must be greater than 0');
            e.preventDefault();
            return false;
        }
    });

    // Current admin interface JavaScript
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Chart.js if stats page
        if (document.getElementById('emailStatsChart')) {
            initializeStatsChart();
        }
    });

    async function initializeStatsChart() {
        try {
            const response = await fetch(`${resendWPMailer.apiUrl}/stats`, {
                headers: {
                    'X-WP-Nonce': resendWPMailer.apiNonce
                }
            });

            const data = await response.json();
            const dailyStats = data.daily;

            const ctx = document.getElementById('emailStatsChart').getContext('2d');
            const labels = dailyStats.map(stat => stat.date);
            const sentData = dailyStats.map(stat => stat.sent);
            const failedData = dailyStats.map(stat => stat.failed);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Successful',
                        data: sentData,
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        tension: 0.1
                    }, {
                        label: 'Failed',
                        data: failedData,
                        borderColor: '#dc3232',
                        backgroundColor: 'rgba(220, 50, 50, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    // Future React initialization can be added here

    // Initialize tooltips
    $('.help-tip').tooltip({
        position: {
            my: 'left center',
            at: 'right+10 center'
        }
    });
});
