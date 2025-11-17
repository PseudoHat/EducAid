<?php
// Opt-in to camera access for this page (used by Permissions-Policy)
if (!defined('ALLOW_CAMERA')) { define('ALLOW_CAMERA', true); }

// Load security headers before any output
require_once __DIR__ . '/../../config/security_headers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the admin is logged in, otherwise redirect to the login page
if (!isset($_SESSION['admin_username'])) {
    header("Location: admin_login.php");
    exit;
}

// Include the database connection to establish the connection
include __DIR__ . '/../../config/database.php';

// Query to get active applicants with a QR code and payroll number
// Ensure only students who are active and have both QR codes and payroll numbers are selected
$qr_res = pg_query($connection, "
    SELECT 
        qr_codes.qr_id, 
        qr_codes.payroll_number, 
        qr_codes.student_id, 
        qr_codes.status AS qr_status, 
        students.first_name, 
        students.last_name, 
        students.status AS student_status
    FROM 
        qr_codes
    JOIN 
        students ON students.student_id = qr_codes.student_id
    WHERE 
        students.status = 'active'  -- Only active students
        AND qr_codes.payroll_number IS NOT NULL  -- Students with a payroll number
        AND qr_codes.unique_id IS NOT NULL  -- Students with a QR code
    ORDER BY qr_codes.created_at DESC
");

// Check if the query is successful
if (!$qr_res) {
    echo "Error executing query: " . pg_last_error($connection);
    exit;
}

// Count results for table rendering
$qr_count = pg_num_rows($qr_res);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scan QR - Admin</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,500,600&display=swap" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/admin/homepage.css" rel="stylesheet">
    <link href="../../assets/css/admin/sidebar.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .qr-center-viewport {
            min-height: 100vh;
            width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f7f6;
        }
        .qr-box {
            max-width: 420px;
            width: 100%;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 36px 28px 32px 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .controls {
            margin: 1rem 0;
        }
        button, select {
            padding: 0.5rem 1rem;
            margin: 0.5rem 0.5rem;
            font-size: 1rem;
            cursor: pointer;
        }
        #result {
            font-family: monospace;
            color: #333;
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <?php include __DIR__ . '/../../includes/admin/admin_sidebar.php'; ?>
        <div class="sidebar-backdrop d-none" id="sidebar-backdrop"></div>
        <section class="home-section" id="page-content-wrapper">
            <nav>
                <div class="sidebar-toggle px-4 py-3">
                    <i class="bi bi-list" id="menu-toggle" aria-label="Toggle Sidebar"></i>
                </div>
            </nav>

            <div class="container py-5">
                <h2>Scan QR</h2>

                <!-- QR Code Scanner -->
                <h3>QR Code Scanner</h3>
                <div class="qr-center-viewport">
                    <div class="qr-box">
                        <div id="reader"></div>
                        <div class="controls">
                            <select id="camera-select">
                                <option value="">Select Camera</option>
                            </select>
                            <br />
                            <button id="start-button">Start Scanner</button>
                            <button id="stop-button" disabled>Stop Scanner</button>
                        </div>

                        <p><strong>Result:</strong> <span id="result">—</span></p>
                    </div>
                </div>

                                <!-- Load QR library from CSP-allowed CDN with fallback -->
                                <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
                                <script>
                                    (function ensureHtml5Qrcode(){
                                        function hasLib(){ return typeof window.Html5Qrcode !== 'undefined'; }
                                        function load(src, cb){ var s=document.createElement('script'); s.src=src; s.async=false; s.onload=cb; s.onerror=cb; document.head.appendChild(s); }
                                        if (hasLib()) return;
                                        setTimeout(function(){
                                            if (!hasLib()) {
                                                load('https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js', function(){
                                                    if (!hasLib()) { console.error('Failed to load html5-qrcode from both CDNs.'); }
                                                });
                                            }
                                        }, 500);
                                    })();
                                </script>
                <script>
                    const startButton = document.getElementById('start-button');
                    const stopButton = document.getElementById('stop-button');
                    const resultSpan = document.getElementById('result');
                    const cameraSelect = document.getElementById('camera-select');
                                        let html5QrCode = null; // lazy init when library is available

                    let currentCameraId = null;

                    // Initialize camera selection WITHOUT requesting permission; enumerate if possible
                                        async function initializeCameraSelection() {
                        startButton.disabled = true;
                        startButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Initializing...';

                        // Wait until the scanner library is available (handle slow CDN)
                        try {
                            await new Promise((resolve, reject) => {
                                const start = Date.now();
                                (function waitLib(){
                                    if (typeof window.Html5Qrcode !== 'undefined') return resolve();
                                    if (Date.now() - start > 7000) return reject(new Error('Scanner library failed to load'));
                                    setTimeout(waitLib, 150);
                                })();
                            });
                        } catch (e) {
                            console.error('Failed to load scanner library:', e);
                            alert('Scanner library failed to load. Please check your connection and reload the page.');
                            startButton.disabled = true;
                            startButton.textContent = 'Scanner Unavailable';
                            return;
                        }

                        // Try to enumerate cameras (may return empty labels before permission)
                        try {
                            const cameras = await Html5Qrcode.getCameras();
                            if (cameras && cameras.length > 0) {
                                console.log(`Found ${cameras.length} camera(s)`);
                                cameraSelect.innerHTML = '<option value="">Select Camera</option>';
                                cameras.forEach(camera => {
                                    const option = document.createElement('option');
                                    option.value = camera.id;
                                    option.text = camera.label || `Camera ${camera.id}`;
                                    cameraSelect.appendChild(option);
                                });

                                const backCam = cameras.find(cam => cam.label && cam.label.toLowerCase().includes('back'));
                                if (backCam) {
                                    cameraSelect.value = backCam.id;
                                    currentCameraId = backCam.id;
                                    console.log('Selected back camera:', backCam.label);
                                } else {
                                    cameraSelect.value = cameras[0].id;
                                    currentCameraId = cameras[0].id;
                                    console.log('Selected first camera:', cameras[0].label || cameras[0].id);
                                }
                            } else {
                                console.warn('No cameras found during initial enumeration (permission may be required).');
                            }
                        } catch (err) {
                            console.warn('Camera list unavailable until permission is granted:', err);
                            // It's fine; we'll request permission on Start
                        }

                        startButton.disabled = false;
                        startButton.textContent = 'Start Scanner';
                    }
                    
                    // Initialize on page load
                    document.addEventListener('DOMContentLoaded', () => {
                        initializeCameraSelection();
                    });

                    cameraSelect.addEventListener('change', () => {
                        currentCameraId = cameraSelect.value;
                        console.log('Camera changed to:', currentCameraId);
                    });

                    startButton.addEventListener('click', async () => {
                        console.log('Start clicked');

                        // Ensure library present and instance created
                        if (typeof window.Html5Qrcode === 'undefined') {
                            alert('Scanner library is not loaded yet. Please wait a moment and try again.');
                            return;
                        }
                        if (!html5QrCode) {
                            try { html5QrCode = new Html5Qrcode("reader"); }
                            catch (e) { console.error('Failed to create scanner instance:', e); alert('Failed to initialize scanner.'); return; }
                        }

                        startButton.disabled = true;
                        startButton.textContent = 'Requesting permission...';
                        stopButton.disabled = true;

                        try {
                            let stream;
                            if (currentCameraId) {
                                // Try to request the selected camera specifically
                                try {
                                    stream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: currentCameraId } } });
                                } catch (err) {
                                    if (err.name === 'OverconstrainedError') {
                                        console.warn('Selected camera not available, falling back to default camera.');
                                        stream = await navigator.mediaDevices.getUserMedia({ video: true });
                                    } else {
                                        throw err;
                                    }
                                }
                            } else {
                                // No camera preselected, request general permission
                                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                            }

                            // Stop the stream immediately after getting permission
                            stream.getTracks().forEach(track => track.stop());

                            // If we didn't have a camera selected, enumerate and choose one now
                            if (!currentCameraId) {
                                const cameras = await Html5Qrcode.getCameras();
                                if (!cameras || cameras.length === 0) {
                                    throw new Error('No cameras found on your device');
                                }
                                cameraSelect.innerHTML = '<option value="">Select Camera</option>';
                                cameras.forEach(camera => {
                                    const option = document.createElement('option');
                                    option.value = camera.id;
                                    option.text = camera.label || `Camera ${camera.id}`;
                                    cameraSelect.appendChild(option);
                                });
                                const backCam = cameras.find(cam => cam.label && cam.label.toLowerCase().includes('back'));
                                if (backCam) {
                                    cameraSelect.value = backCam.id;
                                    currentCameraId = backCam.id;
                                } else {
                                    cameraSelect.value = cameras[0].id;
                                    currentCameraId = cameras[0].id;
                                }
                                console.log('Selected camera after permission:', cameraSelect.options[cameraSelect.selectedIndex]?.text);
                            }

                            console.log('Starting scanner with camera:', currentCameraId);
                            await html5QrCode.start(
                                currentCameraId,
                                { fps: 10, qrbox: { width: 250, height: 250 } },
                                decodedText => {
                                    resultSpan.textContent = decodedText;
                                    console.log('QR Code detected:', decodedText);
                                    html5QrCode.stop();
                                    startButton.disabled = false;
                                    startButton.textContent = 'Start Scanner';
                                    stopButton.disabled = true;
                                },
                                error => {
                                    // Ignore common decode errors (happens frequently during scanning)
                                    if (!error.includes('NotFoundException') && !error.includes('No MultiFormat Readers')) {
                                        console.log('Scanner error:', error);
                                    }
                                }
                            );

                            startButton.disabled = true;
                            startButton.textContent = 'Scanner Running...';
                            stopButton.disabled = false;
                            console.log('Scanner started successfully');

                        } catch (err) {
                            console.error('Failed to start scanning:', err);

                            let errorMessage = 'Failed to start camera. ';
                            if (err.name === 'NotAllowedError' || (err.message && err.message.toLowerCase().includes('permission'))) {
                                errorMessage += 'Camera permission was denied. Click the camera icon in your browser address bar to allow access, then reload or click Start again.';
                            } else if (err.name === 'NotFoundError') {
                                errorMessage += 'Camera not found. Please check if your camera is connected.';
                            } else if (err.name === 'NotReadableError' || (err.message && err.message.toLowerCase().includes('in use'))) {
                                errorMessage += 'Camera is already in use by another application. Please close other apps using the camera.';
                            } else if (err.message && err.message.toLowerCase().includes('secure')) {
                                errorMessage += 'Camera access requires HTTPS. Please use a secure connection.';
                            } else {
                                errorMessage += err.message || 'Unknown error occurred.';
                            }

                            alert(errorMessage);
                            startButton.disabled = false;
                            startButton.textContent = 'Start Scanner';
                            stopButton.disabled = true;
                        }
                    });

                    stopButton.addEventListener('click', () => {
                        console.log('Stopping scanner...');
                        html5QrCode.stop()
                            .then(() => {
                                startButton.disabled = false;
                                startButton.textContent = 'Start Scanner';
                                stopButton.disabled = true;
                                console.log("Scanner stopped successfully");
                            })
                            .catch(err => {
                                console.error("Failed to stop scanning:", err);
                                alert("Failed to stop camera: " + err.message);
                            });
                    });
                </script>

                <!-- Table displaying the QR code status and students info -->
                <h3>QR Code and Student Status</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Payroll Number</th>
                            <th>QR Code Status</th>
                            <th>Student Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all QR codes and display them
                        if ($qr_count > 0) {
                            while ($row = pg_fetch_assoc($qr_res)) {
                                // Determine QR code status: 'Given' if status is 'Done', otherwise 'Pending'
                                $qr_status = ($row['qr_status'] === 'Done') ? 'Given' : 'Pending';
                                
                                // Fetch student status (already fetched as part of the query)
                                $student_status = $row['student_status'];
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['qr_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['payroll_number']) . "</td>";
                                echo "<td>" . htmlspecialchars($qr_status) . "</td>";
                                echo "<td>" . htmlspecialchars($student_status) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>No active students with QR codes and payroll numbers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script src="../../assets/js/admin/sidebar.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
