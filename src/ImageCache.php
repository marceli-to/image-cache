<?php

namespace MarceliTo\ImageCache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class ImageCache
{
    protected ImageManager $manager;
    protected string $cachePath;
    protected int $lifetime;

    public function __construct()
    {
        // Initialize with the GD driver using the correct v3 syntax
        $this->manager = new ImageManager(new GdDriver());
        $this->cachePath = storage_path(config('image-cache.cache_path', 'app/public/cache'));
        $this->lifetime = config('image-cache.lifetime', 43200); // Default to 30 days if not set
        
        // Ensure cache directory exists
        if (!File::exists($this->cachePath)) {
            File::makeDirectory($this->cachePath, 0755, true);
        }
    }

    /**
     * Get or create a cached image
     *
     * @param string $template
     * @param string $filename
     * @param array $params
     * @return string|null
     */
    public function getCachedImage(string $template, string $filename, array $params = []): ?string
    {
        try {
            // Validate inputs
            $this->validateFilename($filename);
            $this->validateTemplate($template);

            // Get the cached image path
            $cachedImagePath = $this->getCachedImagePath($template, $filename, $params);
            
            // Check if the cached image exists
            if (File::exists($cachedImagePath) && (time() - File::lastModified($cachedImagePath) < $this->lifetime * 60)) {
                return $cachedImagePath;
            }
            
            // Find the original image
            $originalImagePath = $this->findOriginalImage($filename);
            if (!$originalImagePath) {
                Log::warning("Original image not found: {$filename}");
                return null;
            }
            
            // Create the cached image
            return $this->createCachedImage($originalImagePath, $cachedImagePath, $template);
        } catch (Exception $e) {
            Log::error("Error getting cached image: {$e->getMessage()}", [
                'template' => $template,
                'filename' => $filename,
                'exception' => $e
            ]);
            throw new RuntimeException("Failed to get cached image: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * Apply a template to an image
     *
     * @param string $template
     * @param string $path
     * @param array $params
     * @return ImageInterface
     */
    protected function applyTemplate(string $template, string $path, array $params = []): ImageInterface
    {
        try {
            $image = $this->manager->read($path);
            
            $templateClass = config("image-cache.templates.{$template}");
            
            if (class_exists($templateClass)) {
                // For crop template, pass all parameters
                if ($template === 'crop') {
                    $filter = new $templateClass(
                        $params['maxSize'] ?? null,
                        $params['maxWidth'] ?? null,
                        $params['maxHeight'] ?? null,
                        $params['coords'] ?? null,
                        $params['ratio'] ?? null
                    );
                } else {
                    // For other templates, only pass width and height
                    $filter = new $templateClass();
                }
                
                return $filter->apply($image);
            }
            
            return $image;
        } catch (Exception $e) {
            Log::error("Error applying template: {$e->getMessage()}", [
                'template' => $template,
                'path' => $path,
                'params' => $params,
                'exception' => $e
            ]);
            throw new RuntimeException("Failed to apply template: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * Find the original image in the configured paths
     *
     * @param string $filename
     * @return string|null
     */
    protected function findOriginalImage(string $filename): ?string
    {
        try {
            // Validate filename
            $this->validateFilename($filename);

            // Get the original image paths
            $paths = config('image-cache.paths', []);
            
            // Check if the original image exists in any of the paths
            foreach ($paths as $path) {
                $filePath = $path . '/' . $filename;
                if (File::exists($filePath)) {
                    return $filePath;
                }
            }
            
            // Log that the image was not found
            Log::warning("Original image not found in any configured paths: {$filename}");
            return null;
        } catch (Exception $e) {
            Log::error("Error finding original image: {$e->getMessage()}", [
                'filename' => $filename,
                'exception' => $e
            ]);
            return null;
        }
    }
    
    /**
     * Generate a unique cache key for the image
     *
     * @param string $template
     * @param string $filename
     * @param array $params
     * @return string
     */
    protected function generateCacheKey(string $template, string $filename, array $params = []): string
    {
        $key = $template . '_' . $filename;
        
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        
        return $key;
    }

    /**
     * Clear all cached images
     *
     * @return bool True if successful, false otherwise
     * @throws RuntimeException When clearing the cache fails
     */
    public function clearAllCache(): bool
    {
        try {
            if (File::exists($this->cachePath)) {
                // Get all subdirectories (templates)
                $directories = File::directories($this->cachePath);
                
                // Delete each template directory and its contents
                foreach ($directories as $directory) {
                    File::deleteDirectory($directory);
                    
                    // Recreate the empty directory
                    $templateName = basename($directory);
                    File::makeDirectory($this->cachePath . '/' . $templateName, 0755, true);
                }
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::error("Error clearing all cache: {$e->getMessage()}", [
                'exception' => $e
            ]);
            throw new RuntimeException("Failed to clear all cache: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Clear cached images for a specific image
     *
     * @param string $filename
     * @return bool
     */
    public function clearImageCache(string $filename): bool
    {
        try {
            // Validate filename
            $this->validateFilename($filename);

            if (File::exists($this->cachePath)) {
                $files = File::allFiles($this->cachePath);
                foreach ($files as $file) {
                    $fileBasename = $file->getFilename();
                    // Check if the filename is part of the cache key
                    if (strpos($fileBasename, '_' . $filename . '_') !== false) {
                        File::delete($file->getPathname());
                    }
                }
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::error("Error clearing image cache: {$e->getMessage()}", [
                'filename' => $filename,
                'exception' => $e
            ]);
            throw new RuntimeException("Failed to clear image cache: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Clear the cache for a specific template
     *
     * @param string $template The template name
     * @return bool True if successful, false otherwise
     * @throws RuntimeException When clearing the cache fails
     */
    public function clearTemplateCache(string $template): bool
    {
        try {
            // Validate template
            $this->validateTemplate($template);

            // Get the template directory
            $templateDir = $this->cachePath . '/' . $template;
            
            if (File::exists($templateDir)) {
                // Delete the entire template directory and its contents
                File::deleteDirectory($templateDir);
                
                // Recreate the empty directory
                File::makeDirectory($templateDir, 0755, true);
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::error("Error clearing template cache: {$e->getMessage()}", [
                'template' => $template,
                'exception' => $e
            ]);
            throw new RuntimeException("Failed to clear template cache: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a cached image
     *
     * @param string $originalImagePath The path to the original image
     * @param string $cachedImagePath The path to the cached image
     * @param string $template The template name
     * @return string|null The path to the cached image or null if creation failed
     * @throws RuntimeException When image processing fails
     */
    protected function createCachedImage(string $originalImagePath, string $cachedImagePath, string $template): ?string
    {
        try {
            // Create the directory if it doesn't exist
            $directory = dirname($cachedImagePath);
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
            
            // Get the template class
            $templateClass = $this->getTemplateClass($template);
            if (!$templateClass) {
                Log::error("Template class not found: {$template}");
                return null;
            }
            
            // Create the image manager
            $manager = new ImageManager(new GdDriver());
            
            // Process the image
            $image = $manager->read($originalImagePath);
            $image = $image->modify($templateClass);
            
            // Save the image
            $image->save($cachedImagePath);
            
            return $cachedImagePath;
        } catch (Exception $e) {
            Log::error("Error creating cached image: {$e->getMessage()}", [
                'originalImagePath' => $originalImagePath,
                'cachedImagePath' => $cachedImagePath,
                'template' => $template,
                'exception' => $e
            ]);
            throw new RuntimeException("Failed to create cached image: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the path to the cached image
     *
     * @param string $template The template name
     * @param string $filename The filename
     * @param array $params Additional parameters
     * @return string The path to the cached image
     */
    protected function getCachedImagePath(string $template, string $filename, array $params = []): string
    {
        // Base path with template
        $basePath = $this->cachePath . '/' . $template;
        
        // For crop template or any template with parameters, create a hash-based subdirectory
        if (!empty($params)) {
            // Create a hash of the parameters to use as a directory name
            // This ensures unique directories for different parameter combinations
            // while avoiding problematic directory names with special characters
            $paramHash = md5(json_encode($params));
            
            // Use the first 2 characters as a subdirectory for better file distribution
            $basePath .= '/' . substr($paramHash, 0, 2);
            
            // Use the rest of the hash as the final directory
            $basePath .= '/' . substr($paramHash, 2);
        }
        
        // Ensure the directory exists
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }
        
        // Return the full path including filename
        return $basePath . '/' . $filename;
    }

    /**
     * Get the template class
     *
     * @param string $template The template name
     * @return ModifierInterface|null The template class or null if not found
     */
    protected function getTemplateClass(string $template): ?ModifierInterface
    {
        try {
            // Get the template class name
            $className = config("image-cache.templates.{$template}");
            
            // Check if the class exists
            if (!class_exists($className)) {
                Log::warning("Template class does not exist: {$className}");
                return null;
            }
            
            // Create the template class
            return new $className();
        } catch (Exception $e) {
            Log::error("Error getting template class: {$e->getMessage()}", [
                'template' => $template,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Validate the filename
     *
     * @param string $filename The filename to validate
     * @return bool True if the filename is valid
     * @throws InvalidArgumentException When filename is invalid
     */
    protected function validateFilename(string $filename): bool
    {
        // Check if the filename is empty
        if (empty($filename)) {
            throw new InvalidArgumentException("Filename cannot be empty");
        }

        // Check if the filename contains only allowed characters
        if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $filename)) {
            throw new InvalidArgumentException("Filename contains invalid characters: {$filename}");
        }

        // Check if the filename contains directory traversal attempts
        if (strpos($filename, '..') !== false) {
            throw new InvalidArgumentException("Directory traversal attempt detected in filename: {$filename}");
        }

        // Check if the filename has a valid extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new InvalidArgumentException("Invalid file extension: {$extension}. Allowed extensions: " . implode(', ', $allowedExtensions));
        }

        return true;
    }

    /**
     * Validate the template
     *
     * @param string $template The template to validate
     * @return bool True if the template is valid
     * @throws InvalidArgumentException When template is invalid
     */
    protected function validateTemplate(string $template): bool
    {
        // Check if the template is empty
        if (empty($template)) {
            throw new InvalidArgumentException("Template cannot be empty");
        }

        // Check if the template contains only allowed characters
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $template)) {
          throw new InvalidArgumentException("Template contains invalid characters: {$template}");
        }

        // Check if the template exists in the configuration
        $templates = array_keys(config('image-cache.templates', []));
        if (!in_array($template, $templates)) {
          throw new InvalidArgumentException("Template not found in configuration: {$template}");
        }

        return true;
    }
}
