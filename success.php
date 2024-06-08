<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
</head>
<body>
    <h1>Payment Successful!</h1>
    <div id="session-details">
        Loading session details...
    </div>

    <script>
        // Lấy session_id từ URL
        const urlParams = new URLSearchParams(window.location.search);
        const sessionId = urlParams.get('session_id');

        if (sessionId) {
            // Gọi endpoint để lấy thông tin chi tiết về phiên
            fetch(`get_session_details.php?session_id=${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('session-details').innerHTML = 'Error: ' + data.error;
                    } else {
                        console.log(data);
                        // Hiển thị thông tin chi tiết về phiên
                        document.getElementById('session-details').innerHTML = `
                            <p><strong>Session ID:</strong> ${data.session_id}</p>
                            ${data.subscription_id ? `<p><strong>Subscription ID:</strong> ${data.subscription_id}</p>` : ''}
                            ${data.current_period_end ? `<p><strong>Current Period End:</strong> ${data.current_period_end}</p>` : ''}
                            <p><strong>Status:</strong> ${data.status}</p>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('session-details').innerHTML = 'Error fetching session details.';
                    console.error('Error:', error);
                });
        } else {
            document.getElementById('session-details').innerHTML = 'No session ID provided.';
        }
    </script>
</body>
</html>
