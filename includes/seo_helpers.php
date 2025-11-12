<?php
/**
 * SEO Helper Functions
 * Utility functions for loading and managing SEO metadata
 */

/**
 * Load SEO metadata for a specific page
 * 
 * @param string $pageKey The page identifier (e.g., 'landing', 'about')
 * @return array SEO metadata for the page
 */
function getSEOData($pageKey) {
    $seoConfig = include __DIR__ . '/../config/seo_config.php';
    
    if (isset($seoConfig[$pageKey])) {
        return $seoConfig[$pageKey];
    }
    
    // Return default if page not found
    return [
        'title' => 'EducAid Scholarship System',
        'description' => 'Educational financial assistance program for students in General Trias, Cavite.',
        'keywords' => 'scholarship, education, financial aid, General Trias, Cavite',
        'image' => '/assets/images/og-default.jpg',
        'type' => 'website'
    ];
}

/**
 * Generate breadcrumbs for SEO
 * 
 * @param array $crumbs Array of breadcrumb items [['name' => 'Home', 'url' => '/'], ...]
 * @return array Formatted breadcrumbs
 */
function generateBreadcrumbs($crumbs) {
    $siteUrl = 'https://www.educ-aid.site';
    $breadcrumbs = [];
    
    foreach ($crumbs as $crumb) {
        $breadcrumbs[] = [
            'name' => $crumb['name'],
            'url' => $siteUrl . $crumb['url']
        ];
    }
    
    return $breadcrumbs;
}

/**
 * Generate FAQ Schema for SEO
 * 
 * @param array $faqs Array of FAQ items [['question' => '', 'answer' => ''], ...]
 * @return string JSON-LD schema
 */
function generateFAQSchema($faqs) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => []
    ];
    
    foreach ($faqs as $faq) {
        $schema['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer']
            ]
        ];
    }
    
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Generate Article Schema for news/announcements
 * 
 * @param array $article Article data
 * @return string JSON-LD schema
 */
function generateArticleSchema($article) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article['title'],
        'description' => $article['description'] ?? '',
        'image' => $article['image'] ?? '',
        'datePublished' => $article['datePublished'] ?? date('c'),
        'dateModified' => $article['dateModified'] ?? date('c'),
        'author' => [
            '@type' => 'Organization',
            'name' => 'EducAid Scholarship Program'
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'EducAid',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => 'https://www.educ-aid.site/assets/images/logo.png'
            ]
        ]
    ];
    
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Generate Event Schema for scholarship events
 * 
 * @param array $event Event data
 * @return string JSON-LD schema
 */
function generateEventSchema($event) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event['name'],
        'description' => $event['description'] ?? '',
        'startDate' => $event['startDate'],
        'endDate' => $event['endDate'] ?? $event['startDate'],
        'location' => [
            '@type' => 'Place',
            'name' => $event['location'] ?? 'General Trias City Hall',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'General Trias City Hall',
                'addressLocality' => 'General Trias',
                'addressRegion' => 'Cavite',
                'postalCode' => '4107',
                'addressCountry' => 'PH'
            ]
        ],
        'organizer' => [
            '@type' => 'Organization',
            'name' => 'EducAid Scholarship Program',
            'url' => 'https://www.educ-aid.site'
        ]
    ];
    
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Clean and truncate text for meta descriptions
 * 
 * @param string $text Text to clean
 * @param int $maxLength Maximum length (default 160 for meta descriptions)
 * @return string Cleaned text
 */
function cleanMetaText($text, $maxLength = 160) {
    // Strip HTML tags
    $text = strip_tags($text);
    
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Trim
    $text = trim($text);
    
    // Truncate if too long
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength - 3) . '...';
    }
    
    return $text;
}

/**
 * Generate dynamic page title
 * 
 * @param string $pageTitle Page-specific title
 * @param bool $includeSiteName Include site name in title
 * @return string Formatted title
 */
function generatePageTitle($pageTitle, $includeSiteName = true) {
    $siteName = 'EducAid Scholarship System';
    
    if ($includeSiteName && $pageTitle !== $siteName) {
        return $pageTitle . ' | ' . $siteName;
    }
    
    return $pageTitle;
}
