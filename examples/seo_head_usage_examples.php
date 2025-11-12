<?php
/**
 * EXAMPLE: How to Use SEO Head Component
 * 
 * Copy this code to the top of any page to add SEO metadata
 */

// METHOD 1: Using SEO Config File (Recommended)
// -------------------------------------------------

// Include helper functions
require_once __DIR__ . '/includes/seo_helpers.php';

// Load SEO data for this page
$seoData = getSEOData('landing'); // Options: 'landing', 'about', 'howitworks', 'requirements', etc.

// Set variables for SEO head
$pageTitle = $seoData['title'];
$pageDescription = $seoData['description'];
$pageKeywords = $seoData['keywords'];
$pageImage = 'https://www.educ-aid.site' . $seoData['image'];
$pageUrl = 'https://www.educ-aid.site/website/landingpage.php';
$pageType = $seoData['type'];

// Optional: Add breadcrumbs
$breadcrumbs = generateBreadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Landing Page', 'url' => '/website/landingpage.php']
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/seo_head.php'; ?>
    
    <!-- Your other CSS and JS files -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Your page content -->
</body>
</html>


<?php
/**
 * METHOD 2: Custom SEO Data
 * -------------------------------------------------
 * Use this when you need page-specific SEO data
 */

// Set custom variables
$pageTitle = 'Custom Page Title';
$pageDescription = 'This is a custom description for this specific page';
$pageKeywords = 'custom, keywords, for, this, page';
$pageImage = 'https://www.educ-aid.site/assets/images/custom-image.jpg';
$pageUrl = 'https://www.educ-aid.site/custom-page.php';
$pageType = 'website';
$pageAuthor = 'EducAid Team';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/seo_head.php'; ?>
</head>
<body>
    <!-- Your page content -->
</body>
</html>


<?php
/**
 * METHOD 3: Dynamic Content (e.g., Announcements)
 * -------------------------------------------------
 * Use this for dynamic pages with database content
 */

// Fetch announcement from database
// $announcement = fetchAnnouncementFromDB($id);

// Example data
$announcement = [
    'title' => 'New Scholarship Distribution Schedule',
    'content' => 'The scholarship distribution for this semester will be held on...',
    'date' => '2025-11-15',
    'image' => '/uploads/announcements/schedule.jpg'
];

// Include helper
require_once __DIR__ . '/includes/seo_helpers.php';

// Set SEO variables
$pageTitle = $announcement['title'];
$pageDescription = cleanMetaText($announcement['content'], 160);
$pageKeywords = 'scholarship distribution, schedule, announcement';
$pageImage = 'https://www.educ-aid.site' . $announcement['image'];
$pageUrl = 'https://www.educ-aid.site/announcement.php?id=123';
$pageType = 'article';

// Add Article Schema
$articleSchema = generateArticleSchema([
    'title' => $announcement['title'],
    'description' => $announcement['content'],
    'image' => $pageImage,
    'datePublished' => $announcement['date'],
    'dateModified' => $announcement['date']
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/seo_head.php'; ?>
    
    <!-- Add Article Schema -->
    <script type="application/ld+json">
    <?php echo $articleSchema; ?>
    </script>
</head>
<body>
    <!-- Your page content -->
</body>
</html>


<?php
/**
 * METHOD 4: FAQ Page with Schema
 * -------------------------------------------------
 */

require_once __DIR__ . '/includes/seo_helpers.php';

// Load SEO data
$seoData = getSEOData('faq');
$pageTitle = $seoData['title'];
$pageDescription = $seoData['description'];
$pageKeywords = $seoData['keywords'];
$pageImage = 'https://www.educ-aid.site' . $seoData['image'];
$pageUrl = 'https://www.educ-aid.site/website/faq.php';
$pageType = 'FAQPage';

// FAQ data
$faqs = [
    [
        'question' => 'Who is eligible for the scholarship?',
        'answer' => 'Students enrolled in General Trias schools from elementary to college who meet the income requirements.'
    ],
    [
        'question' => 'What documents are required?',
        'answer' => 'Educational Assistance Form, grades, ID picture, certificate of indigency, and letter to the mayor.'
    ],
    [
        'question' => 'When is the application deadline?',
        'answer' => 'The deadline varies per semester. Check the announcements page for current deadlines.'
    ]
];

// Generate FAQ Schema
$faqSchema = generateFAQSchema($faqs);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/seo_head.php'; ?>
    
    <!-- Add FAQ Schema -->
    <script type="application/ld+json">
    <?php echo $faqSchema; ?>
    </script>
</head>
<body>
    <!-- Your FAQ content -->
</body>
</html>


<?php
/**
 * METHOD 5: Event Page (Distribution Schedule)
 * -------------------------------------------------
 */

require_once __DIR__ . '/includes/seo_helpers.php';

// Event data
$event = [
    'name' => 'Scholarship Distribution - Batch 1',
    'description' => 'First batch of scholarship distribution for this semester',
    'startDate' => '2025-12-01T09:00:00+08:00',
    'endDate' => '2025-12-01T17:00:00+08:00',
    'location' => 'General Trias City Hall'
];

// SEO variables
$pageTitle = $event['name'];
$pageDescription = $event['description'];
$pageKeywords = 'scholarship distribution, event, schedule, General Trias';
$pageImage = 'https://www.educ-aid.site/assets/images/og-distribution.jpg';
$pageUrl = 'https://www.educ-aid.site/event.php?id=1';
$pageType = 'Event';

// Generate Event Schema
$eventSchema = generateEventSchema($event);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/seo_head.php'; ?>
    
    <!-- Add Event Schema -->
    <script type="application/ld+json">
    <?php echo $eventSchema; ?>
    </script>
</head>
<body>
    <!-- Your event content -->
</body>
</html>
