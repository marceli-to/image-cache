<?php

namespace MarceliTo\ImageCache\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MarceliTo\ImageCache\ImageCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class ImageController extends Controller
{
    /**
     * The image cache instance.
     */
    protected ImageCache $imageCache;

    /**
     * Create a new controller instance.
     */
    public function __construct(ImageCache $imageCache)
    {
        $this->imageCache = $imageCache;
    }

    /**
     * Get the image response.
     *
     * @param Request $request The request instance
     * @param string $template The template name
     * @param string $filename The filename
     * @return Response The response
     */
    public function getResponse(Request $request, string $template, string $filename): Response
    {
        try {
            // Validate inputs
            $this->validateTemplate($template);
            $this->validateFilename($filename);

            // Get the cached image
            $cachedImagePath = $this->imageCache->getCachedImage($template, $filename);

            // If the cached image doesn't exist, return a 404 response
            if (!$cachedImagePath || !File::exists($cachedImagePath)) {
                Log::warning("Cached image not found", [
                    'template' => $template,
                    'filename' => $filename
                ]);
                return response()->make('Image not found', 404);
            }

            // Get the file contents and mime type
            $fileContents = File::get($cachedImagePath);
            $mimeType = $this->getMimeType($cachedImagePath);

            // Return the response
            return response()->make($fileContents, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => File::size($cachedImagePath),
                'Cache-Control' => 'max-age=' . config('image-cache.browser_cache_time', 3600) . ', public',
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + config('image-cache.browser_cache_time', 3600))
            ]);
        } catch (InvalidArgumentException $e) {
            Log::warning("Invalid input for image response: {$e->getMessage()}", [
                'template' => $template ?? 'unknown',
                'filename' => $filename ?? 'unknown',
                'exception' => $e
            ]);
            return response()->make('Invalid input: ' . $e->getMessage(), 400);
        } catch (Exception $e) {
            Log::error("Error generating image response: {$e->getMessage()}", [
                'template' => $template ?? 'unknown',
                'filename' => $filename ?? 'unknown',
                'exception' => $e
            ]);
            return response()->make('Server error', 500);
        }
    }

    /**
     * Get the crop image response.
     *
     * @param Request $request The request instance
     * @param string $filename The filename
     * @param string|null $maxSize The maximum size
     * @param string|null $coords The coordinates
     * @param string|null $ratio The ratio
     * @return Response The response
     */
    public function getCropResponse(Request $request, string $filename, ?string $maxSize = null, ?string $coords = null, ?string $ratio = null): Response
    {
        try {
            // Validate inputs
            $this->validateFilename($filename);
            $this->validateMaxSize($maxSize);
            $this->validateCoords($coords);
            $this->validateRatio($ratio);

            // Convert maxSize to integer if provided
            $maxSizeInt = $maxSize !== null ? (int) $maxSize : null;

            // Get the cached image
            $cachedImagePath = $this->imageCache->getCachedImage('crop', $filename, [
                'maxSize' => $maxSizeInt,
                'coords' => $coords,
                'ratio' => $ratio
            ]);

            // If the cached image doesn't exist, return a 404 response
            if (!$cachedImagePath || !File::exists($cachedImagePath)) {
                Log::warning("Cached crop image not found", [
                    'filename' => $filename,
                    'maxSize' => $maxSize,
                    'coords' => $coords,
                    'ratio' => $ratio
                ]);
                return response()->make('Image not found', 404);
            }

            // Get the file contents and mime type
            $fileContents = File::get($cachedImagePath);
            $mimeType = $this->getMimeType($cachedImagePath);

            // Return the response
            return response()->make($fileContents, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => File::size($cachedImagePath),
                'Cache-Control' => 'max-age=' . config('image-cache.browser_cache_time', 3600) . ', public',
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + config('image-cache.browser_cache_time', 3600))
            ]);
        } catch (InvalidArgumentException $e) {
            Log::warning("Invalid input for crop image response: {$e->getMessage()}", [
                'filename' => $filename ?? 'unknown',
                'maxSize' => $maxSize,
                'coords' => $coords,
                'ratio' => $ratio,
                'exception' => $e
            ]);
            return response()->make('Invalid input: ' . $e->getMessage(), 400);
        } catch (Exception $e) {
            Log::error("Error generating crop image response: {$e->getMessage()}", [
                'filename' => $filename ?? 'unknown',
                'maxSize' => $maxSize,
                'coords' => $coords,
                'ratio' => $ratio,
                'exception' => $e
            ]);
            return response()->make('Server error', 500);
        }
    }

    /**
     * Get the crop image with dimensions response.
     *
     * @param Request $request The request instance
     * @param string $filename The filename
     * @param string|null $maxWidth The maximum width
     * @param string|null $maxHeight The maximum height
     * @param string|null $coords The coordinates
     * @param string|null $ratio The ratio
     * @return Response The response
     */
    public function getCropWithDimensionsResponse(Request $request, string $filename, ?string $maxWidth = null, ?string $maxHeight = null, ?string $coords = null, ?string $ratio = null): Response
    {
        try {
            // Validate inputs
            $this->validateFilename($filename);
            $this->validateMaxDimension($maxWidth, 'maxWidth');
            $this->validateMaxDimension($maxHeight, 'maxHeight');
            $this->validateCoords($coords);
            $this->validateRatio($ratio);

            // Convert dimensions to integers if provided
            $maxWidthInt = $maxWidth !== null ? (int) $maxWidth : null;
            $maxHeightInt = $maxHeight !== null ? (int) $maxHeight : null;

            // Get the cached image
            $cachedImagePath = $this->imageCache->getCachedImage('crop', $filename, [
                'maxWidth' => $maxWidthInt,
                'maxHeight' => $maxHeightInt,
                'coords' => $coords,
                'ratio' => $ratio
            ]);

            // If the cached image doesn't exist, return a 404 response
            if (!$cachedImagePath || !File::exists($cachedImagePath)) {
                Log::warning("Cached crop image with dimensions not found", [
                    'filename' => $filename,
                    'maxWidth' => $maxWidth,
                    'maxHeight' => $maxHeight,
                    'coords' => $coords,
                    'ratio' => $ratio
                ]);
                return response()->make('Image not found', 404);
            }

            // Get the file contents and mime type
            $fileContents = File::get($cachedImagePath);
            $mimeType = $this->getMimeType($cachedImagePath);

            // Return the response
            return response()->make($fileContents, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => File::size($cachedImagePath),
                'Cache-Control' => 'max-age=' . config('image-cache.browser_cache_time', 3600) . ', public',
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + config('image-cache.browser_cache_time', 3600))
            ]);
        } catch (InvalidArgumentException $e) {
            Log::warning("Invalid input for crop image with dimensions response: {$e->getMessage()}", [
                'filename' => $filename ?? 'unknown',
                'maxWidth' => $maxWidth,
                'maxHeight' => $maxHeight,
                'coords' => $coords,
                'ratio' => $ratio,
                'exception' => $e
            ]);
            return response()->make('Invalid input: ' . $e->getMessage(), 400);
        } catch (Exception $e) {
            Log::error("Error generating crop image with dimensions response: {$e->getMessage()}", [
                'filename' => $filename ?? 'unknown',
                'maxWidth' => $maxWidth,
                'maxHeight' => $maxHeight,
                'coords' => $coords,
                'ratio' => $ratio,
                'exception' => $e
            ]);
            return response()->make('Server error', 500);
        }
    }

    /**
     * Get the mime type of a file.
     *
     * @param string $path The file path
     * @return string The mime type
     */
    protected function getMimeType(string $path): string
    {
        // Get the file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Map extensions to mime types
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        // Return the mime type or a default
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Validate the filename.
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
     * Validate the template.
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

    /**
     * Validate the maximum size.
     *
     * @param string|null $maxSize The maximum size to validate
     * @return bool True if the maximum size is valid
     * @throws InvalidArgumentException When maximum size is invalid
     */
    protected function validateMaxSize(?string $maxSize): bool
    {
        // If maxSize is null, it's valid
        if ($maxSize === null) {
            return true;
        }

        // Check if maxSize is a positive integer
        if (!ctype_digit($maxSize) || (int) $maxSize <= 0) {
            throw new InvalidArgumentException("Maximum size must be a positive integer: {$maxSize}");
        }

        // Check if maxSize is within allowed range
        $maxAllowed = config('image-cache.max_size', 2400);
        if ((int) $maxSize > $maxAllowed) {
            throw new InvalidArgumentException("Maximum size exceeds allowed value ({$maxAllowed}): {$maxSize}");
        }

        return true;
    }

    /**
     * Validate a maximum dimension.
     *
     * @param string|null $dimension The dimension to validate
     * @param string $name The name of the dimension
     * @return bool True if the dimension is valid
     * @throws InvalidArgumentException When dimension is invalid
     */
    protected function validateMaxDimension(?string $dimension, string $name): bool
    {
        // If dimension is null, it's valid
        if ($dimension === null) {
            return true;
        }

        // Check if dimension is a positive integer
        if (!ctype_digit($dimension) || (int) $dimension <= 0) {
            throw new InvalidArgumentException("{$name} must be a positive integer: {$dimension}");
        }

        // Check if dimension is within allowed range
        $maxAllowed = $name === 'maxWidth' 
            ? config('image-cache.max_width', 2400) 
            : config('image-cache.max_height', 1600);
            
        if ((int) $dimension > $maxAllowed) {
            throw new InvalidArgumentException("{$name} exceeds allowed value ({$maxAllowed}): {$dimension}");
        }

        return true;
    }

    /**
     * Validate the coordinates.
     *
     * @param string|null $coords The coordinates to validate
     * @return bool True if the coordinates are valid
     * @throws InvalidArgumentException When coordinates are invalid
     */
    protected function validateCoords(?string $coords): bool
    {
        // If coords is null, it's valid
        if ($coords === null) {
            return true;
        }

        // Check if coords match the expected format (width,height,x,y)
        if (!preg_match('/^\d+,\d+,\d+,\d+$/', $coords)) {
            throw new InvalidArgumentException("Coordinates must be in format: width,height,x,y");
        }

        return true;
    }

    /**
     * Validate the ratio.
     *
     * @param string|null $ratio The ratio to validate
     * @return bool True if the ratio is valid
     * @throws InvalidArgumentException When ratio is invalid
     */
    protected function validateRatio(?string $ratio): bool
    {
        // If ratio is null, it's valid
        if ($ratio === null) {
            return true;
        }

        // Check if ratio matches the expected format (width:height)
        if (!preg_match('/^\d+:\d+$/', $ratio)) {
            throw new InvalidArgumentException("Ratio must be in format: width:height");
        }

        return true;
    }
}
