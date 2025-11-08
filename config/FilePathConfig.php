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
            'letter_mayor'       // Localhost: letter_mayor, Railway: Letter
        ],
        'student' => [
            'enrollment_forms',  // Localhost: enrollment_forms, Railway: EAF
            'grades',            // Same on both
            'id_pictures',       // Localhost: id_pictures, Railway: ID
            'indigency',         // Same on both (case may differ)
            'letter_to_mayor'    // Localhost: letter_to_mayor, Railway: Letter
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
        'letter_mayor' => 'Letter',
        'letter_to_mayor' => 'Letter'
    ];
    
    /**
     * Reverse mapping: Railway folder names to standard names
     */
    private $standardFolderMap = [
        'eaf' => 'enrollment_forms',
        'grades' => 'grades',
        'id' => 'id_pictures',
        'indigency' => 'indigency',
        'letter' => 'letter_mayor'
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
        return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exports';
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
     */
    public function resolveRelativePath($relativePath) {
        // Remove leading 'assets/uploads/' if present
        $relativePath = preg_replace('#^/?assets/uploads/?#', '', $relativePath);
        
        // Build absolute path
        return $this->baseUploadsDir . $relativePath;
    }
    
    /**
     * Convert absolute path to relative path (for database storage)
     * Returns path in format: assets/uploads/...
     */
    public function getRelativePath($absolutePath) {
        // Normalize separators
        $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        
        // Remove base path
        $relativePath = str_replace($this->baseUploadsDir, '', $absolutePath);
        
        // Convert to forward slashes for database consistency
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        
        // Ensure it starts with assets/uploads/
        if (strpos($relativePath, 'assets/uploads/') !== 0) {
            $relativePath = 'assets/uploads/' . ltrim($relativePath, '/');
        }
        
        return $relativePath;
    }
    
    /**
     * Build file path with proper separators
     */
    public function buildPath(...$parts) {
        return implode(DIRECTORY_SEPARATOR, $parts);
    }
    
    /**
     * Find existing folder path by checking all possible name variations
     * Useful when migrating between Railway and localhost
     * @param string $baseFolder 'temp' or 'student'
     * @param string $standardName Standard folder name (enrollment_forms, id_pictures, etc.)
     * @return string|null Path to existing folder, or null if not found
     */
    public function findExistingFolder($baseFolder, $standardName) {
        $basePath = $baseFolder === 'temp' ? $this->getTempPath() : $this->getStudentPath();
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        
        // Get all possible folder name variations
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
     * @return array ['enrollment_forms' => '/path/to/EAF', 'grades' => '/path/to/Grades', ...]
     */
    public function getAllDocumentFolders($baseFolder) {
        $folders = [];
        $structure = $baseFolder === 'temp' ? $this->folderStructure['temp'] : $this->folderStructure['student'];
        
        foreach ($structure as $standardName) {
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
            'directory_separator' => DIRECTORY_SEPARATOR,
            'temp_path' => $this->getTempPath(),
            'student_path' => $this->getStudentPath(),
            'archived_path' => $this->getArchivedStudentsPath(),
            'distributions_path' => $this->getDistributionsPath()
        ];
    }
}
