<!-- Critical CSS to prevent FOUC (Flash of Unstyled Content) -->
<style>
  /* Immediate styling to prevent flash */
  html { 
    visibility: visible; 
    opacity: 1; 
  }
  
  body { 
    margin: 0; 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    opacity: 1;
    transition: opacity 0.15s ease-in;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  
  /* Prevent layout shift from fixed navbar */
  .navbar { 
    min-height: 70px; 
    background: #fff;
  }
  
  /* Prevent content jump */
  .container { 
    max-width: 1320px; 
    margin: 0 auto; 
    padding: 0 15px; 
  }
  
  /* Loading state */
  body.loading {
    overflow: hidden;
  }
  
  /* Hide elements that need JS until ready */
  .needs-js { 
    opacity: 0; 
    transition: opacity 0.3s ease; 
  }
  
  .needs-js.ready { 
    opacity: 1; 
  }
  
  /* Skeleton placeholder (if elements not loaded) */
  .skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s ease-in-out infinite;
  }
  
  @keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }
  
  /* Prevent flash of invisible text (FOIT) for web fonts */
  .hero h1, .hero p {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  
  /* Smooth fade-in for entire page */
  body {
    animation: fadeIn 0.2s ease-in;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  /* Prevent layout shift for images */
  img {
    display: block;
    max-width: 100%;
    height: auto;
  }
</style>
