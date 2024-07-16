<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link rel="shortcut icon" type="image/png" href="../images/icon_logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .loader {
            width: 50px;
            height: 165px;
            position: relative;
        }

        .loader::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            transform: translate(-50%, 0);
            width: 16px;
            height: 16px;
            background: #00C2FF;
            border-radius: 50%;
            animation: bounce 2s linear infinite;
        }

        .loader::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            margin: auto;
            height: 48px;
            width: 48px;
            background: #BB82FE;
            border-radius: 4px;
            animation: rotate 2s linear infinite;
        }

        @keyframes bounce {

            0%,
            50%,
            100% {
                transform: translate(-50%, 0px);
                height: 20px;
            }

            20% {
                transform: translate(-25%, 85px);
                height: 28px;
            }

            25% {
                transform: translate(-25%, 110px);
                height: 12px;
            }

            70% {
                transform: translate(-75%, 85px);
                height: 28px;
            }

            75% {
                transform: translate(-75%, 108px);
                height: 12px;
            }
        }

        @keyframes rotate {

            0%,
            50%,
            100% {
                transform: rotate(0deg)
            }

            25% {
                transform: rotate(90deg)
            }

            75% {
                transform: rotate(-90deg)
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="p-5 d-flex flex-column align-items-center justify-content-center">
                        <img class="img-fluid" src="../images/logo.svg" alt="">
                    </div>
                    <div class="col-lg-7 pt-3 d-flex flex-column align-items-center justify-content-center" style="
                background-image: url('../images/paymentsuccess.svg'); background-repeat: no-repeat;
                 background-size: cover; background-position: center;
                 height:400px;
                border-radius: 32px; " id="session-details">

                        <span class="loader"></span>

                    </div>
                </div>
            </div>
        </section>
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

                            <div class="mb-3">
                                <div class="card-body">
                                    <div class="pt-2 pb-2 text-center">
                                        <img src="../images/success.png" alt="">
                                    </div>

                                    <div class="col-12 text-center">
                                        <h3 class="text-white">Payment Successful</h3>
                                        <div class="pt-3">
                                            <h6 class="text-white">You have signed up for a ${data.plan_name} ${data.month}</h6>
                                            <h6 class="text-white">Payment Successful</h6>
                                            ${data.current_period_end ? `<div class="text-white">Validity period : ${data.current_period_end}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-10 rounded" style="background: white;">
                                <div class="text-center pt-2 pb-2">
                                    License key : <span id="license-key">${data.license_key}</span>  <img id="copy-button" src="../images/coppy.svg" onclick="copyLicenseKey()" alt="Copy">
                                </div>
                            </div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('session-details').innerHTML = 'Error fetching session details.';
                    console.error('Error:', error);
                });
        } else {
            document.getElementById('session-details').innerHTML = 'No session ID provided.';
        }

        function copyLicenseKey() {
            const licenseKey = document.getElementById('license-key').innerText;
            navigator.clipboard.writeText(licenseKey).then(function() {
                alert('License key copied to clipboard');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
</body>

</html>