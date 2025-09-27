<?php
// includes/FileHandler.php
class FileHandler {
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct() {
        $this->uploadDir = UPLOAD_DIR;
        $this->allowedTypes = ALLOWED_FILE_TYPES;
        $this->maxFileSize = MAX_FILE_SIZE;
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function handleUpload($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('Invalid file upload');
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('No file was uploaded');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File is too large');
            default:
                throw new Exception('Unknown upload error');
        }
        
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File is too large. Maximum size is ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $this->allowedTypes)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes));
        }
        
        $fileName = $this->generateUniqueFileName($file['name']);
        $filePath = $this->uploadDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        return [
            'filename' => $fileName,
            'original_name' => $file['name'],
            'file_path' => $filePath,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'extension' => $fileExtension
        ];
    }
    
    public function extractTextContent($filePath, $fileType) {
        switch ($fileType) {
            case 'txt':
            case 'md':
                return file_get_contents($filePath);
                
            case 'pdf':
                return $this->extractPdfText($filePath);
                
            case 'docx':
                return $this->extractDocxText($filePath);
                
            default:
                throw new Exception('Unsupported file type for text extraction');
        }
    }
    
   private function extractPdfText($filePath) {
    $filePathEscaped = escapeshellarg($filePath);
    $content = shell_exec("pdftotext {$filePathEscaped} -");
    if ($content === null) {
        throw new Exception('Failed to extract text from PDF. Please ensure pdftotext is installed.');
    }
    return $content;
}
    private function extractDocxText($filePath) {
        // Basic DOCX text extraction using ZIP
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            throw new Exception('Failed to open DOCX file');
        }
        
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($content === false) {
            throw new Exception('Failed to extract content from DOCX');
        }
        
        // Remove XML tags and decode entities
        $content = strip_tags($content);
        $content = html_entity_decode($content);
        
        return $content;
    }
    
    private function generateUniqueFileName($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        
        return $baseName . '_' . uniqid() . '_' . time() . '.' . $extension;
    }
    
    public function deleteFile($fileName) {
        $filePath = $this->uploadDir . $fileName;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    public function getFileInfo($fileName) {
        $filePath = $this->uploadDir . $fileName;
        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }
        
        return [
            'filename' => $fileName,
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'mime_type' => mime_content_type($filePath),
            'modified_time' => filemtime($filePath)
        ];
    }
}
?>
