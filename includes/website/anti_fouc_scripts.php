<!-- Anti-FOUC Scripts: Prevent flash of unstyled content -->
<script>
  // Immediately mark page as loading
  (function() {
    document.documentElement.classList.add('loading');
  })();
  
  // Mark elements with .needs-js as ready when DOM loads
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.needs-js').forEach(function(el) {
      el.classList.add('ready');
    });
    
    // Remove loading class
    document.documentElement.classList.remove('loading');
  });
  
  // Ensure smooth transition when all resources loaded
  window.addEventListener('load', function() {
    document.body.style.opacity = '1';
    
    // Trigger any lazy-loaded content
    if (typeof window.lazyLoadInstance !== 'undefined') {
      window.lazyLoadInstance.update();
    }
  });
  
  // Handle page transitions (back/forward navigation)
  window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
      // Page restored from bfcache, ensure visibility
      document.body.style.opacity = '1';
      document.documentElement.classList.remove('loading');
    }
  });
</script>
