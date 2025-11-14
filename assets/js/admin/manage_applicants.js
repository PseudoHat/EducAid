// Manage Applicants - Enhanced with portrait mode detection
document.addEventListener("DOMContentLoaded", function() {
    console.log('Manage applicants page loaded - forms ready');
    
    // Detect orientation and apply dynamic classes
    function handleOrientationChange() {
        const body = document.body;
        const isPortrait = window.matchMedia("(orientation: portrait)").matches;
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            body.classList.toggle('mobile-portrait', isPortrait);
            body.classList.toggle('mobile-landscape', !isPortrait);
            
            // Adjust table container for better mobile experience
            const tableResponsive = document.querySelector('.table-responsive');
            if (tableResponsive && isPortrait) {
                tableResponsive.style.maxHeight = '65vh';
                tableResponsive.style.overflowY = 'auto';
                tableResponsive.style.overflowX = 'hidden';
            } else if (tableResponsive) {
                tableResponsive.style.maxHeight = '';
                tableResponsive.style.overflowX = 'auto';
            }
            
            // Apply truncation for portrait mode
            if (isPortrait) {
                applyTextTruncation();
            } else {
                removeTextTruncation();
            }
        } else {
            body.classList.remove('mobile-portrait', 'mobile-landscape');
            removeTextTruncation();
        }
    }
    
    // Function to truncate text in Name, Contact, and Email columns
    function applyTextTruncation() {
        const table = document.querySelector('.table');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            
            // Apply to Name (1st column), Contact (2nd column), and Email (3rd column)
            [0, 1, 2].forEach(index => {
                if (cells[index] && !cells[index].querySelector('.text-truncatable')) {
                    const cellText = cells[index].textContent.trim();
                    
                    // Only truncate if text is longer than threshold
                    const threshold = index === 0 ? 20 : (index === 1 ? 15 : 25); // Name: 20, Contact: 15, Email: 25
                    
                    if (cellText.length > threshold) {
                        const wrapper = document.createElement('span');
                        wrapper.className = 'text-truncatable truncated';
                        wrapper.textContent = cellText;
                        wrapper.setAttribute('data-full-text', cellText);
                        wrapper.setAttribute('data-column', index);
                        
                        cells[index].textContent = '';
                        cells[index].appendChild(wrapper);
                        
                        // Add click event listener
                        wrapper.addEventListener('click', toggleTextExpansion);
                    }
                }
            });
        });
    }
    
    // Function to remove truncation (for landscape or desktop)
    function removeTextTruncation() {
        const truncatedElements = document.querySelectorAll('.text-truncatable');
        
        truncatedElements.forEach(element => {
            const fullText = element.getAttribute('data-full-text');
            if (fullText) {
                const parent = element.parentElement;
                parent.textContent = fullText;
            }
        });
    }
    
    // Toggle text expansion on click
    function toggleTextExpansion(event) {
        const element = event.currentTarget;
        
        if (element.classList.contains('truncated')) {
            element.classList.remove('truncated');
            element.classList.add('expanded');
        } else if (element.classList.contains('expanded')) {
            element.classList.remove('expanded');
            element.classList.add('truncated');
        }
    }
    
    // Run on load
    handleOrientationChange();
    
    // Listen for orientation changes
    window.addEventListener('orientationchange', function() {
        setTimeout(handleOrientationChange, 100);
    });
    
    // Listen for resize events (for browser zoom or window resize)
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleOrientationChange, 250);
    });
    
    // Add smooth scroll for table on mobile
    if (window.innerWidth <= 600) {
        const tableWrapper = document.querySelector('.table-responsive');
        if (tableWrapper) {
            tableWrapper.style.scrollBehavior = 'smooth';
        }
    }
    
    // Enhance touch interaction for mobile
    if ('ontouchstart' in window) {
        const actionButtons = document.querySelectorAll('.btn-info.btn-sm');
        actionButtons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });
            button.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });
        });
    }
    
    // Watch for dynamically loaded content (AJAX updates)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                const isPortrait = window.matchMedia("(orientation: portrait)").matches;
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && isPortrait) {
                    applyTextTruncation();
                }
            }
        });
    });
    
    // Observe the table body for changes
    const tableBody = document.querySelector('.table tbody');
    if (tableBody) {
        observer.observe(tableBody, {
            childList: true,
            subtree: true
        });
    }
});
