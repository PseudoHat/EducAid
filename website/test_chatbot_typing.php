<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chatbot Typing Indicator TEST</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Landing page CSS (contains chatbot styles) -->
  <link rel="stylesheet" href="../assets/css/website/landing_page.css">
  
  <style>
    body {
      padding: 2rem;
      background: #f8f9fa;
    }
    .test-info {
      max-width: 800px;
      margin: 0 auto 2rem;
      padding: 1rem;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .test-info h2 {
      color: #0d6efd;
      margin-bottom: 1rem;
    }
    .test-info ul {
      margin-bottom: 0;
    }
    .test-info code {
      background: #f8f9fa;
      padding: 0.2rem 0.4rem;
      border-radius: 3px;
      color: #d63384;
    }
  </style>
</head>
<body>

<div class="test-info">
  <h2>🧪 Chatbot Typing Indicator TEST Page</h2>
  <p><strong>Purpose:</strong> Test the fixed typing indicator that should appear at the BOTTOM of the chat conversation.</p>
  
  <h3>What was wrong:</h3>
  <ul>
    <li>Typing indicator was a static element in the HTML</li>
    <li>New messages pushed it up visually</li>
    <li>Scroll happened AFTER typing was hidden</li>
    <li>Result: Students saw typing indicator at the TOP of viewport</li>
  </ul>
  
  <h3>The fix:</h3>
  <ul>
    <li>✅ Typing indicator is now created dynamically</li>
    <li>✅ Removed from DOM and re-appended to the END each time</li>
    <li>✅ Scroll happens immediately after showing typing</li>
    <li>✅ Added console logging to debug position</li>
    <li>✅ Clean removal after bot response</li>
  </ul>
  
  <h3>Test instructions:</h3>
  <ol>
    <li>Click the "Chat with EducAid" button in the bottom right</li>
    <li>Type any message and press Enter or click Send</li>
    <li><strong>Watch the bottom of the chat</strong> - "EducAid Assistant is typing..." should appear there</li>
    <li>Open browser DevTools Console to see position logs</li>
    <li>Try multiple messages to confirm consistent bottom placement</li>
  </ol>
  
  <p><strong>Files used in this test:</strong></p>
  <ul>
    <li><code>includes/website/chatbot_widget_test.php</code> - Widget without static typing element</li>
    <li><code>assets/js/website/chatbot_shared_test.js</code> - Fixed JavaScript logic</li>
  </ul>
</div>

<!-- Include the TEST chatbot widget -->
<?php include __DIR__ . '/../includes/website/chatbot_widget_test.php'; ?>

<!-- jQuery (if needed by other scripts) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- TEST Chatbot Script -->
<script src="../assets/js/website/chatbot_shared_test.js"></script>

</body>
</html>
