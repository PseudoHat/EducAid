<?php
/**
 * SEO Head Component for EducAid
 * Include this file in the <head> section of your pages
 * 
 * Usage:
 * <?php 
 *   $pageTitle = "About Us";
 *   $pageDescription = "Learn about EducAid scholarship program";
 *   $pageKeywords = "scholarship, about, education";
 *   $pageImage = "https://www.educ-aid.site/assets/images/og-about.jpg";
 *   $pageUrl = "https://www.educ-aid.site/website/aboutpage.php";
 *   include __DIR__ . '/includes/seo_head.php';
 * ?>
 */

// Default values if not set
$siteUrl = 'https://www.educ-aid.site';
$siteName = 'EducAid Scholarship System';
$defaultTitle = 'EducAid - Educational Financial Assistance Program | General Trias, Cavite';
$defaultDescription = 'Apply for educational scholarships and financial assistance in General Trias, Cavite. Supporting students with tuition, supplies, and educational needs.';
$defaultKeywords = 'scholarship, education, financial aid, General Trias, Cavite, student assistance, tuition support, educational program';
$defaultImage = $siteUrl . '/assets/images/og-default.jpg';

// Use provided values or defaults
$pageTitle = isset($pageTitle) ? $pageTitle : $defaultTitle;
$pageDescription = isset($pageDescription) ? $pageDescription : $defaultDescription;
$pageKeywords = isset($pageKeywords) ? $pageKeywords : $defaultKeywords;
$pageImage = isset($pageImage) ? $pageImage : $defaultImage;
$pageUrl = isset($pageUrl) ? $pageUrl : $siteUrl;
$pageType = isset($pageType) ? $pageType : 'website';
$pageAuthor = isset($pageAuthor) ? $pageAuthor : 'EducAid Scholarship Program';

// Construct full title
$fullTitle = ($pageTitle === $defaultTitle) ? $pageTitle : $pageTitle . ' | ' . $siteName;
?>

<!-- Primary Meta Tags -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo htmlspecialchars($fullTitle); ?></title>
<meta name="title" content="<?php echo htmlspecialchars($fullTitle); ?>">
<meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
<meta name="author" content="<?php echo htmlspecialchars($pageAuthor); ?>">
<meta name="robots" content="index, follow">
<meta name="language" content="English">
<meta name="revisit-after" content="7 days">

<!-- Canonical URL -->
<link rel="canonical" href="<?php echo htmlspecialchars($pageUrl); ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?php echo htmlspecialchars($pageType); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($pageUrl); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($fullTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($pageImage); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="<?php echo htmlspecialchars($siteName); ?>">
<meta property="og:locale" content="en_PH">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="<?php echo htmlspecialchars($pageUrl); ?>">
<meta name="twitter:title" content="<?php echo htmlspecialchars($fullTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($pageImage); ?>">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?php echo $siteUrl; ?>/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $siteUrl; ?>/assets/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo $siteUrl; ?>/assets/images/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo $siteUrl; ?>/assets/images/apple-touch-icon.png">

<!-- Manifest for PWA (optional) -->
<link rel="manifest" href="<?php echo $siteUrl; ?>/manifest.json">

<!-- Theme Color -->
<meta name="theme-color" content="#1e40af">
<meta name="msapplication-TileColor" content="#1e40af">

<!-- Additional SEO Tags -->
<meta name="rating" content="general">
<meta name="distribution" content="global">
<meta name="coverage" content="Worldwide">

<!-- Geo Tags for Local SEO -->
<meta name="geo.region" content="PH-CAV">
<meta name="geo.placename" content="General Trias, Cavite">
<meta name="geo.position" content="14.3866;120.8806">
<meta name="ICBM" content="14.3866, 120.8806">

<!-- Schema.org Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "GovernmentOrganization",
  "name": "<?php echo htmlspecialchars($siteName); ?>",
  "description": "<?php echo htmlspecialchars($defaultDescription); ?>",
  "url": "<?php echo $siteUrl; ?>",
  "logo": "<?php echo $siteUrl; ?>/assets/images/logo.png",
  "image": "<?php echo htmlspecialchars($pageImage); ?>",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "General Trias City Hall",
    "addressLocality": "General Trias",
    "addressRegion": "Cavite",
    "postalCode": "4107",
    "addressCountry": "PH"
  },
  "contactPoint": {
    "@type": "ContactPoint",
    "contactType": "Customer Service",
    "availableLanguage": ["English", "Filipino"]
  },
  "areaServed": {
    "@type": "City",
    "name": "General Trias",
    "containedIn": {
      "@type": "State",
      "name": "Cavite"
    }
  }
}
</script>

<!-- Breadcrumb Schema (if applicable) -->
<?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    <?php 
    foreach ($breadcrumbs as $index => $crumb) {
        echo json_encode([
            "@type" => "ListItem",
            "position" => $index + 1,
            "name" => $crumb['name'],
            "item" => $crumb['url']
        ]);
        if ($index < count($breadcrumbs) - 1) echo ',';
    }
    ?>
  ]
}
</script>
<?php endif; ?>

<!-- Preconnect to external domains for performance -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://www.google.com">
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

<!-- Alternate Languages (if multilingual support added) -->
<!-- <link rel="alternate" hreflang="en" href="<?php echo $pageUrl; ?>"> -->
<!-- <link rel="alternate" hreflang="tl" href="<?php echo $pageUrl; ?>?lang=tl"> -->
