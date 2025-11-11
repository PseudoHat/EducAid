<?php
/**
 * FilePathConfig - Centralized File Path Management
 * 
 * Automatically detects Railway vs localhost and provides correct paths
 * for all file operations across the application.
 * 
 * Usage:
 *   $config = FilePathConfig::getInstance();
 *   $uploadsDir = $config->getUploadsPath();
 *   $tempPath = $config->getTempPath('enrollment_forms');
 *   $studentPath = $config->getStudentPath('grades');
 */

class FilePathConfig {
    private static $instance = null;
    private $isRailway;
    private $baseUploadsPath;
    private $baseUploadsDir;
    
    
    
    /**
     * Folder structure mapping
     * Railway uses different names than localhost
     */
    private $folderStructure = [
        'temp' => [
            'enrollment_forms',  // Localhost: enrollment_forms, Railway: EAF
            'grades',            // Same on both
            'id_pictures',       // Localhost: id_pictures, Railway: ID
            'indigency',         // Same on both (case may differ)
            'letter_to_mayor'    // Localhost: letter_to_mayor, Railway: Letter (standardized)
        ],
        'student' => [
            'enrollment_forms',  // Localhost: enrollment_forms, Railway: EAF
            'grades',            // Same on both
            'id_pictures',       // Localhost: id_pictures, Railway: ID
            'indigency',         // Same on both (case may differ)
            'letter_to_mayor'    // Localhost: letter_to_mayor, Railway: Letter (standardized)
        ],
        'archived_students' => [],
        'distributions' => [],
        'announcements' => [],
        'municipal_logos' => []
    ];
    
    /**
     * Railway folder name mapping (Railway uses different names)
     */
    private $railwayFolderMap = [
        'enrollment_forms' => 'EAF',
        'grades' => 'Grades',
        'id_pictures' => 'ID',
        'indigency' => 'Indigency',
        'letter_to_mayor' => 'Letter'  // Standardized: letter_to_mayor (not letter_mayor)
    ];
    
    /**
     * Reverse mapping: Railway folder names to standard names
     */
    private $standardFolderMap = [
        'eaf' => 'enrollment_forms',
        'grades' => 'grades',
        'id' => 'id_pictures',
        'indigency' => 'indigency',
        'letter' => 'letter_to_mayor'  // Standardized: letter_to_mayor (not letter_mayor)
    ];
    
    private function __construct() {
        // Detect environment (Railway has /mnt/assets/uploads/ volume)
        $this->isRailway = file_exists('/mnt/assets/uploads/');
        
        if ($this->isRailway) {
            // Railway environment - use mounted volume
            $this->baseUploadsPath = '/mnt/assets/uploads';
            $this->baseUploadsDir = '/mnt/assets/uploads/';
        } else {
            // Local development environment
            $this->baseUploadsPath = dirname(__DIR__) . '/assets/uploads';
            $this->baseUploadsDir = dirname(__DIR__) . '/assets/uploads/';
        }
        
        // Normalize directory separator for current OS
        $this->baseUploadsPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->baseUploadsPath);
        $this->baseUploadsDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->baseUploadsDir);
        
        // Ensure trailing separator
        if (substr($this->baseUploadsDir, -1) !== DIRECTORY_SEPARATOR) {
            $this->baseUploadsDir .= DIRECTORY_SEPARATOR;
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if running on Railway
     */
    public function isRailway() {
        return $this->isRailway;
    }
    
    /**
     * Convert standard folder name to environment-specific name
     * @param string $standardName Standard folder name (enrollment_forms, id_pictures, etc.)
     * @return string Environment-specific folder name
     */
    public function getFolderName($standardName) {
        if ($this->isRailway && isset($this->railwayFolderMap[$standardName])) {
            return $this->railwayFolderMap[$standardName];
        }
        return $standardName;
    }
    
    /**
     * Convert Railway folder name to standard name
     * @param string $railwayName Railway folder name (EAF, ID, Letter, etc.)
     * @return string Standard folder name
     */
    public function getStandardFolderName($railwayName) {
        $lower = strtolower($railwayName);
        if (isset($this->standardFolderMap[$lower])) {
            return $this->standardFolderMap[$lower];
        }
        return strtolower($railwayName);
    }
    
    /**
     * Get all folder name variations for a document type
     * Useful for scanning directories that might use either naming convention
     * @param string $standardName Standard folder name
     * @return array Array of possible folder names
     */
    public function getFolderNameVariations($standardName) {
        $variations = [$standardName];
        
        if (isset($this->railwayFolderMap[$standardName])) {
            $variations[] = $this->railwayFolderMap[$standardName];
            $variations[] = strtolower($this->railwayFolderMap[$standardName]);
            $variations[] = strtoupper($this->railwayFolderMap[$standardName]);
        }
        
        return array_unique($variations);
    }
    
    /**
     * Get base uploads path (without trailing slash)
     */
    public function getUploadsPath() {
        return $this->baseUploadsPath;
    }
    
    /**
     * Get base uploads directory (with trailing slash)
     */
    public function getUploadsDir() {
        return $this->baseUploadsDir;
    }
    
    /**
     * Get temp folder path
     * @param string $subfolder Specific subfolder (enrollment_forms, grades, etc.) - uses standard names
     * @return string Full path with environment-specific folder name
     */
    public function getTempPath($subfolder = null) {
        $path = $this->baseUploadsDir . 'temp';
        if ($subfolder) {
            // Convert to environment-specific folder name
            $folderName = $this->getFolderName($subfolder);
            $path .= DIRECTORY_SEPARATOR . $folderName;
        }
        return $path;
    }
    
    /**
     * Get student folder path
     * @param string $subfolder Specific subfolder (enrollment_forms, grades, etc.) - uses standard names
     * @return string Full path with environment-specific folder name
     */
    public function getStudentPath($subfolder = null) {
        $path = $this->baseUploadsDir . 'student';
        if ($subfolder) {
            // Convert to environment-specific folder name
            $folderName = $this->getFolderName($subfolder);
            $path .= DIRECTORY_SEPARATOR . $folderName;
        }
        return $path;
    }
    
    /**
     * Get archived students folder path
     */
    public function getArchivedStudentsPath() {
        return $this->baseUploadsDir . 'archived_students';
    }
    
    /**
     * Get distributions folder path
     */
    public function getDistributionsPath() {
        return $this->baseUploadsDir . 'distributions';
    }
    
    /**
     * Get data exports folder path (for student data export ZIP files)
     */
    public function getDataExportsPath() {
        // Railway: /mnt/assets/data/exports
        // Localhost: C:\xampp\htdocs\EducAid\data\exports
        if ($this->isRailway) {
            return '/mnt/assets/data/exports';
        }
        // Store in application directory, not parent
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exports';
    }
    
    /**
     * Get announcements folder path
     */
    public function getAnnouncementsPath() {
        return $this->baseUploadsDir . 'announcements';
    }
    
    /**
     * Get municipal logos folder path
     */
    public function getMunicipalLogosPath() {
        return $this->baseUploadsDir . 'municipal_logos';
    }
    
    /**
     * Sanitize a relative path to prevent directory traversal and other unsafe segments.
     * Converts separators, strips null bytes, resolves '.' and '..', and removes drive letters.
     * @param string $relativePath
     * @return string Safe relative path using the current OS directory separator
     */
    private function sanitizeRelativePath($relativePath) {
        // Remove null bytes
        $relativePath = str_replace("\0", '', (string)$relativePath);
        // Normalize slashes to '/'
        $relativePath = str_replace('\\', '/', $relativePath);
        // Remove leading slashes
        $relativePath = ltrim($relativePath, '/');
        // Resolve '.' and '..' segments
        $parts = [];
        foreach (explode('/', $relativePath) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            // Block attempts to include drive letters or protocols
            if (strpos($part, ':') !== false) {
                continue;
            }
            $parts[] = $part;
        }
        return implode(DIRECTORY_SEPARATOR, $parts);
    }
    
    /**
     * Ensure all required directories exist
     * Creates missing directories with proper permissions
     */
    public function ensureDirectoryStructure() {
        $dirsToCreate = [];
        
        // Main directories
        $dirsToCreate[] = $this->baseUploadsDir;
        $dirsToCreate[] = $this->getTempPath();
        $dirsToCreate[] = $this->getStudentPath();
        $dirsToCreate[] = $this->getArchivedStudentsPath();
        $dirsToCreate[] = $this->getDistributionsPath();
        $dirsToCreate[] = $this->getAnnouncementsPath();
    $dirsToCreate[] = $this->getMunicipalLogosPath();
    // Data exports directory (outside assets/uploads)
    $dirsToCreate[] = $this->getDataExportsPath();
        
        // Temp subfolders
        foreach ($this->folderStructure['temp'] as $folder) {
            $dirsToCreate[] = $this->getTempPath($folder);
        }
        
        // Student subfolders
        foreach ($this->folderStructure['student'] as $folder) {
            $dirsToCreate[] = $this->getStudentPath($folder);
        }
        
        $created = [];
        $errors = [];
        
        foreach ($dirsToCreate as $dir) {
            if (!is_dir($dir)) {
                if (@mkdir($dir, 0755, true)) {
                    $created[] = $dir;
                } else {
                    $errors[] = $dir;
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'created' => $created,
            'errors' => $errors
        ];
    }
    
    /**
     * Convert relative path to absolute system path
     * Handles both Railway and localhost paths
     * @param string $relativePath Relative path to convert
     * @return string Absolute system path
     */
    public function resolveRelativePath($relativePath = '') {
        // Remove leading 'assets/uploads/' if present
    $relativePath = preg_replace('#^/?assets/uploads/?#', '', $relativePath);
    // Sanitize to avoid path traversal
    $relativePath = $this->sanitizeRelativePath((string)$relativePath);
    // Build absolute path
    return rtrim($this->baseUploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
    }
    
    /**
     * Convert absolute path to relative path (for database storage)
     * Returns path in format: assets/uploads/...
     * @param string $absolutePath Absolute system path to convert
     * @return string Relative path in format: assets/uploads/...
     */
    public function getRelativePath($absolutePath = '') {
        // Normalize separators
        $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        
        // If path is under uploads, produce assets/uploads/... form
        if (strpos($absolutePath, $this->baseUploadsDir) === 0) {
            $relativePath = str_replace($this->baseUploadsDir, '', $absolutePath);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            if (strpos($relativePath, 'assets/uploads/') !== 0) {
                $relativePath = 'assets/uploads/' . ltrim($relativePath, '/');
            }
            return $relativePath;
        }
        
        // If path is under data exports, return data/exports/... for clarity
        $exportsPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getDataExportsPath());
        if (strpos($absolutePath, $exportsPath) === 0) {
            $relativePath = str_replace($exportsPath, '', $absolutePath);
            $relativePath = 'data/exports/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
            return $relativePath;
        }
        
        // Fallback: return normalized forward-slash path without forcing a prefix
        return ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $absolutePath), '/');
    }
    
    /**
     * Build file path with proper separators
     * @param string ...$parts Path components to join
     * @return string Complete path with proper directory separators
     */
    public function buildPath(...$parts) {
        if (empty($parts)) return '';
        return implode(DIRECTORY_SEPARATOR, $parts);
    }
    
    /**
     * Find existing folder path by checking all possible name variations
     * Useful when migrating between Railway and localhost
     * @param string $baseFolder 'temp' or 'student'
     * @param string $standardName Standard folder name (enrollment_forms, id_pictures, etc.)
     * @return string|null Path to existing folder, or null if not found
     */
    public function findExistingFolder($baseFolder = 'temp', $standardName = '') {
        /** @var string $basePath */
        $basePath = $baseFolder === 'temp' ? $this->getTempPath() : $this->getStudentPath();
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        
        // Get all possible folder name variations
        /** @var array<string> $variations */
        $variations = $this->getFolderNameVariations($standardName);
        
        foreach ($variations as $variation) {
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $variation;
            if (is_dir($fullPath)) {
                return $fullPath;
            }
        }
        
        return null;
    }
    
    /**
     * Get all document folders with their actual names on the filesystem
     * Returns array with standard names as keys and actual paths as values
     * @param string $baseFolder 'temp' or 'student'
     * @return array<string, string> Array with standard names as keys and actual paths as values
     */
    public function getAllDocumentFolders($baseFolder = 'temp') {
        /** @var array<string, string> $folders */
        $folders = [];
        /** @var array<string> $structure */
        $structure = $baseFolder === 'temp' ? $this->folderStructure['temp'] : $this->folderStructure['student'];
        
        foreach ($structure as $standardName) {
            /** @var string|null $path */
            $path = $this->findExistingFolder($baseFolder, $standardName);
            if ($path !== null) {
                $folders[$standardName] = $path;
            } else {
                // Return expected path even if it doesn't exist yet
                $folders[$standardName] = ($baseFolder === 'temp' ? $this->getTempPath($standardName) : $this->getStudentPath($standardName));
            }
        }
        
        return $folders;
    }
    
    /**
     * Get environment info for debugging
     */
    public function getDebugInfo() {
        return [
            'environment' => $this->isRailway ? 'Railway' : 'Localhost',
            'base_uploads_path' => $this->baseUploadsPath,
            'base_uploads_dir' => $this->baseUploadsDir,
            'data_exports_path' => $this->getDataExportsPath(),
            'directory_separator' => DIRECTORY_SEPARATOR,
            'temp_path' => $this->getTempPath(),
            'student_path' => $this->getStudentPath(),
            'archived_path' => $this->getArchivedStudentsPath(),
            'distributions_path' => $this->getDistributionsPath()
        ];
    }
}
