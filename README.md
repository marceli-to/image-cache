# Intervention Image Cache

A simple image caching package for Laravel using Intervention Image v3.

## Installation

You can install the package via composer:

```bash
composer require marceli-to/intervention-image-cache
```

## Intervention Image v3 Compatibility

This package is built for Intervention Image v3, which has a significantly different API compared to v2. If you're upgrading from a package that used Intervention Image v2, please note these key differences:

- The v3 API uses interfaces like `ImageInterface` and `ModifierInterface`
- Image manipulation methods return a new image instance rather than modifying the original
- The driver system has changed (GD is used by default in this package)

For more details on Intervention Image v3, please refer to the [official documentation](https://image.intervention.io/v3).

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=image-cache-config
```

This will create a `config/image-cache.php` file where you can configure:

- Cache path
- Cache lifetime
- Image search paths
- Available templates
- Route configuration

## Features

### Modern PHP Type Declarations

The package uses modern PHP 7.4+ type declarations throughout the codebase for improved code quality and IDE support:

- Parameter type hints for all method parameters
- Return type declarations for all methods
- Property type declarations for class properties

### Robust Error Handling

The package includes comprehensive error handling:

- Proper exception handling with try/catch blocks
- Detailed logging using Laravel's Log facade
- Appropriate HTTP responses for different error scenarios
- Specific exception types for different error conditions

### Input Validation

All user inputs are thoroughly validated:

- Filename validation to prevent directory traversal attacks
- Template validation to ensure templates exist
- Parameter validation for dimensions, coordinates, and ratios
- Sanitization of all inputs before processing

## Usage

### Basic Usage

```php
use MarceliTo\ImageCache\Facades\ImageCache;

// Get a cached image
$path = ImageCache::getCachedImage('large', 'image.jpg');

// Display the image in a view
<img src="{{ asset('storage/cache/images/' . basename($path)) }}" alt="Image">
```

### In a Controller

```php
use MarceliTo\ImageCache\Facades\ImageCache;

class ImageController extends Controller
{
    public function show($template, $filename)
    {
        try {
            $path = ImageCache::getCachedImage($template, $filename);
            
            if (!$path) {
                return response()->make('Image not found', 404);
            }
            
            return response()->file($path);
        } catch (InvalidArgumentException $e) {
            // Handle validation errors
            return response()->make('Invalid input: ' . $e->getMessage(), 400);
        } catch (Exception $e) {
            // Handle unexpected errors
            Log::error("Error in image controller: {$e->getMessage()}", [
                'exception' => $e
            ]);
            return response()->make('Server error', 500);
        }
    }
}
```

### In Views

The package automatically registers the necessary routes, so you can use it directly in your views:

```html
<img src="/img/thumbnail/image.jpg" alt="Image">

<!-- With cropping using maxSize (single dimension) -->
<img src="/img/crop/image.jpg/800/100,150,500,300" alt="Image">

<!-- With cropping using width and height -->
<img src="/img/crop/image.jpg/800/600/100,150,500,300" alt="Image">

<!-- With aspect ratio -->
<img src="/img/crop/image.jpg/800/600/100,150,500,300/16:9" alt="Image">
```

The URL format for standard templates is:
```
/img/{template}/{filename}
```

The URL format for crop with maxSize is:
```
/img/crop/{filename}/{maxSize?}/{coords?}/{ratio?}
```

The URL format for crop with dimensions is:
```
/img/crop/{filename}/{maxWidth?}/{maxHeight?}/{coords?}/{ratio?}
```

Where:
- `template`: One of the templates defined in your config (e.g., 'large', 'small', 'thumbnail')
- `filename`: The name of the image file to process
- `maxSize`: (Optional) Maximum size for the output image
- `maxWidth`: (Optional) Maximum width for the output image
- `maxHeight`: (Optional) Maximum height for the output image
- `coords`: (Optional) Comma-separated string in the format `width,height,x,y` for cropping
- `ratio`: (Optional) Aspect ratio in the format `width:height` (e.g., '16:9')

**Important notes about inputs:**
- Filenames must contain only alphanumeric characters, underscores, hyphens, slashes, and dots
- File extensions must be one of: jpg, jpeg, png, gif, webp
- Coordinates must be in the format `width,height,x,y` with all values being positive integers
- Ratio must be in the format `width:height` with both values being positive integers
- Maximum dimensions must be positive integers and within the configured limits

### Custom Controller

If you prefer to use your own controller, you can disable the automatic route registration in the config file:

```php
// config/image-cache.php
'register_routes' => false,
```

Then create your own route and controller:

```php
// routes/web.php
Route::get('/img/{template}/{filename}/{maxW?}/{maxH?}/{coords?}', [ImageController::class, 'getResponse']);

// App\Http\Controllers\ImageController.php
public function getResponse(Request $request, string $template, string $filename, ?string $maxW = null, ?string $maxH = null, ?string $coords = null): Response
{
    try {
        // Validate inputs
        $this->validateTemplate($template);
        $this->validateFilename($filename);
        
        $params = [];
        
        if ($maxW) {
            $this->validateMaxDimension($maxW, 'maxWidth');
            $params['maxWidth'] = (int) $maxW;
        }
        
        if ($maxH) {
            $this->validateMaxDimension($maxH, 'maxHeight');
            $params['maxHeight'] = (int) $maxH;
        }
        
        if ($coords) {
            $this->validateCoords($coords);
            $params['coords'] = $coords;
        }
        
        $path = ImageCache::getCachedImage($template, $filename, $params);
        
        if (!$path || !File::exists($path)) {
            return response()->make('Image not found', 404);
        }
        
        $fileContents = File::get($path);
        $mimeType = $this->getMimeType($path);
        
        return response()->make($fileContents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => File::size($path),
            'Cache-Control' => 'max-age=' . config('image-cache.browser_cache_time', 3600) . ', public'
        ]);
    } catch (InvalidArgumentException $e) {
        return response()->make('Invalid input: ' . $e->getMessage(), 400);
    } catch (Exception $e) {
        Log::error("Error generating image response: {$e->getMessage()}", [
            'exception' => $e
        ]);
        return response()->make('Server error', 500);
    }
}
```

### Programmatic Usage

You can also use the package programmatically:

```php
use MarceliTo\ImageCache\Facades\ImageCache;
use InvalidArgumentException;
use RuntimeException;

try {
    // Get a cached image
    $path = ImageCache::getCachedImage('large', 'image.jpg', [
        'maxWidth' => 1200,
        'maxHeight' => 800
    ]);

    // Get a cached image with cropping
    $path = ImageCache::getCachedImage('crop', 'image.jpg', [
        'maxWidth' => 800,
        'maxHeight' => 600,
        'coords' => '500,300,100,150',  // Format: width,height,x,y
        'ratio' => '16:9'
    ]);
    
    if (!$path) {
        // Handle image not found
    }
} catch (InvalidArgumentException $e) {
    // Handle validation errors
    Log::warning("Invalid input: {$e->getMessage()}");
} catch (RuntimeException $e) {
    // Handle runtime errors
    Log::error("Error processing image: {$e->getMessage()}");
} catch (Exception $e) {
    // Handle unexpected errors
    Log::error("Unexpected error: {$e->getMessage()}");
}
```

### Clearing the Cache

You can clear the cache using the provided Artisan command:

```bash
# Clear all cached images
php artisan image:clear-cache

# Clear cached images for a specific template
php artisan image:clear-cache large
```

Or programmatically:

```php
use MarceliTo\ImageCache\Facades\ImageCache;

try {
    // Clear all cached images
    $success = ImageCache::clearAllCache();

    // Clear cached images for a specific template
    $success = ImageCache::clearTemplateCache('large');
    
    // Clear cached images for a specific file
    $success = ImageCache::clearImageCache('image.jpg');
} catch (Exception $e) {
    Log::error("Error clearing cache: {$e->getMessage()}");
}
```

## Custom Templates

You can create your own templates by:

1. Creating a class that implements `Intervention\Image\Interfaces\ModifierInterface`
2. Adding the template to your config file

Example template class:

```php
<?php

namespace App\ImageTemplates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

class CustomTemplate implements ModifierInterface
{
    /**
     * Maximum width for the image
     */
    protected int $maxWidth = 1000;
    
    /**
     * Maximum height for the image
     */
    protected int $maxHeight = 800;
    
    /**
     * Apply filter to image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Get image orientation
        $orientation = $image->height() > $image->width() ? 'portrait' : 'landscape';
        
        // Scale down the image based on orientation while maintaining aspect ratio
        if ($orientation === 'landscape') {
            return $image->scaleDown(width: $this->maxWidth);
        } else {
            return $image->scaleDown(height: $this->maxHeight);
        }
    }
}
```

Then add it to your config:

```php
// config/image-cache.php
'templates' => [
    'custom' => \App\ImageTemplates\CustomTemplate::class,
    // ...other templates
],
```

## Error Handling

The package includes comprehensive error handling:

### Exception Types

- `InvalidArgumentException`: Thrown when input validation fails
- `RuntimeException`: Thrown when file operations or image processing fails
- `Exception`: Caught for any unexpected errors

### Logging

All errors and warnings are logged using Laravel's Log facade:

- Warning level: Input validation failures, image not found
- Error level: File operation failures, image processing errors

### HTTP Responses

The package returns appropriate HTTP responses:

- 400 Bad Request: For invalid inputs
- 404 Not Found: When images can't be found
- 500 Server Error: For unexpected errors

## Security

The package includes several security features:

- **Path Sanitization**: Prevents directory traversal attacks
- **Input Validation**: Validates all user inputs before processing
- **File Extension Validation**: Only allows specific image file extensions
- **Error Message Sanitization**: Ensures error messages don't expose sensitive information

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
