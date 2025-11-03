# PowerShell script to fix inline chatbot typing indicator across all pages
# Run this from the EducAid root directory

Write-Host "Fixing chatbot typing indicators in all website pages..." -ForegroundColor Cyan

$files = @(
    "website\about.php",
    "website\contact.php",
    "website\how-it-works.php",
    "website\requirements.php"
)

foreach ($file in $files) {
    $filePath = Join-Path $PWD $file
    
    if (Test-Path $filePath) {
        Write-Host "Processing $file..." -ForegroundColor Yellow
        
        $content = Get-Content $filePath -Raw
        
        # Remove the static typing indicator div
        $content = $content -replace '<div class="ea-typing" id="eaTyping">[^<]*</div>', '<!-- Typing indicator will be dynamically inserted at the bottom -->'
        
        # Replace typing references in JavaScript
        $content = $content -replace 'const typing = document\.getElementById\(''eaTyping''\);', ''
        $content = $content -replace 'typing\.style\.display = ''block'';', 'showTyping();'
        $content = $content -replace 'typing\.style\.display = ''none'';', 'hideTyping();'
        
        # Add the typing functions if not present
        if ($content -notmatch 'function showTyping\(\)') {
            $typingFunctions = @"

  let typingElement = null;

  function scrollToBottom(){
    if(body){
      setTimeout(() => {
        body.scrollTop = body.scrollHeight;
      }, 10);
    }
  }

  function createTypingIndicator(){
    if(!typingElement){
      typingElement = document.createElement('div');
      typingElement.className = 'ea-typing';
      typingElement.innerHTML = 'EducAid Assistant is typing...';
      typingElement.style.display = 'none';
    }
    return typingElement;
  }

  function showTyping(){
    const typing = createTypingIndicator();
    if(typing.parentNode){
      typing.parentNode.removeChild(typing);
    }
    body.appendChild(typing);
    typing.style.display = 'block';
    scrollToBottom();
  }

  function hideTyping(){
    if(typingElement && typingElement.parentNode){
      typingElement.style.display = 'none';
      typingElement.parentNode.removeChild(typingElement);
      typingElement = null;
    }
  }
"@
            # Insert before the sendMsg function
            $content = $content -replace '(async function sendMsg\(\))', "$typingFunctions`r`n`r`n  `$1"
        }
        
        # Save the file
        Set-Content -Path $filePath -Value $content -NoNewline
        Write-Host "  ✓ Fixed $file" -ForegroundColor Green
    } else {
        Write-Host "  ✗ File not found: $file" -ForegroundColor Red
    }
}

Write-Host "`nDone! All chatbot typing indicators fixed." -ForegroundColor Green
Write-Host "Please hard refresh your browser (Ctrl+F5) to see the changes." -ForegroundColor Cyan
