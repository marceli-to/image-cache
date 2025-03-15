<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;
use Illuminate\Support\Facades\Log;

class Crop implements ModifierInterface
{
	/**
	 * Maximum size for the image
	 */
	protected ?int $maxSize = 2400;

	/**
	 * Maximum width for the image
	 */
	protected ?int $maxWidth = 2400;

	/**
	 * Maximum height for the image
	 */
	protected ?int $maxHeight = 1600;

	/**
	 * Coordinates for cropping (width,height,x,y)
	 */
	protected ?string $coords = null;

	/**
	 * Aspect ratio
	 */
	protected ?string $ratio = null;

	/**
	 * Orientation of the image (landscape or portrait)
	 */
	protected string $orientation = 'landscape';

	/**
	 * Constructor with optional parameters
	 *
	 * @param int|null $maxSize Maximum size for the image
	 * @param int|null $maxWidth Maximum width for the image
	 * @param int|null $maxHeight Maximum height for the image
	 * @param string|null $coords Coordinates for cropping (width,height,x,y)
	 * @param string|null $ratio Aspect ratio (width:height or width/height)
	 */
	public function __construct(?int $maxSize = null, ?int $maxWidth = null, ?int $maxHeight = null, ?string $coords = null, ?string $ratio = null)
	{
		// Set parameters using null coalescing operator
		$this->maxSize = $maxSize ?? $this->maxSize;
		$this->maxWidth = $maxWidth ?? $this->maxWidth;
		$this->maxHeight = $maxHeight ?? $this->maxHeight;
		$this->coords = $coords;
    $this->ratio = $ratio;
	}

	/**
	 * Apply filter to image
	 *
	 * @param ImageInterface $image The image to modify
	 * @return ImageInterface The modified image
	 */
	public function apply(ImageInterface $image): ImageInterface
	{
		// Get image dimensions and determine orientation
		$this->updateOrientation($image);
		
		// First crop the image if valid coordinates are provided
		// Explicitly check for the '0,0,0,0' case to avoid 1x1 pixel crops
		if ($this->coords && $this->coords != '0,0,0,0') {
			$image = $this->cropImage($image);
		}
		// If ratio is provided but no specific coordinates, apply ratio crop
		elseif ($this->ratio) {
			$image = $this->cropToRatio($image);
		}
		
		// Then scale the image based on max dimensions
		$image = $this->scaleImage($image);
		
		return $image;
	}
	
	/**
	 * Update the orientation property based on image dimensions
	 *
	 * @param ImageInterface $image The image to check
	 */
	protected function updateOrientation(ImageInterface $image): void
	{
		$width = $image->width();
		$height = $image->height();
		
		$this->orientation = $height > $width ? 'portrait' : 'landscape';
	}
	
	/**
	 * Crop the image based on coordinates
	 *
	 * @param ImageInterface $image The image to crop
	 * @return ImageInterface The cropped image
	 */
	protected function cropImage(ImageInterface $image): ImageInterface
	{
		// Parse coordinates in width,height,x,y format
		list($cropWidth, $cropHeight, $cropX, $cropY) = explode(',', $this->coords);
		
		// Convert to integer values
		$cropWidth = (int)$cropWidth;
		$cropHeight = (int)$cropHeight;
		$cropX = (int)$cropX;
		$cropY = (int)$cropY;
		
		// Ensure crop dimensions are valid
		if ($cropWidth <= 0) $cropWidth = 1;
		if ($cropHeight <= 0) $cropHeight = 1;
		
		// Ensure crop area is within image boundaries
		$width = $image->width();
		$height = $image->height();
		
		if ($cropX + $cropWidth > $width) $cropWidth = $width - $cropX;
		if ($cropY + $cropHeight > $height) $cropHeight = $height - $cropY;
		
		// Crop the image
		try {
			$image = $image->crop($cropWidth, $cropHeight, $cropX, $cropY);
			
			// Update orientation based on the cropped image
			$this->updateOrientation($image);
		} catch (\Exception $e) {
			Log::error("Error during crop operation: " . $e->getMessage(), [
				'x' => $cropX,
				'y' => $cropY,
				'width' => $cropWidth,
				'height' => $cropHeight,
				'exception' => $e
			]);
			// Continue with the original image if cropping fails
		}
		
		return $image;
	}
	
	/**
	 * Crop the image to match the specified aspect ratio
	 *
	 * @param ImageInterface $image The image to crop
	 * @return ImageInterface The cropped image
	 */
	protected function cropToRatio(ImageInterface $image): ImageInterface
	{
		// Get current image dimensions
		$origWidth = $image->width();
		$origHeight = $image->height();
		
		// Parse the ratio string (can be in format width:height, width/height, or widthxheight)
		$ratio = str_replace([':', 'x'], '/', $this->ratio);
		
		// Check if ratio contains the separator
		if (!str_contains($ratio, '/')) {
			Log::warning("Invalid ratio format: {$this->ratio}. Missing separator (/, :, or x)");
			return $image;
		}
		
		list($ratioWidth, $ratioHeight) = explode('/', $ratio);
		
		// Convert to integer values and ensure they're valid
		$ratioWidth = (int)$ratioWidth;
		$ratioHeight = (int)$ratioHeight;
		
		if ($ratioWidth <= 0 || $ratioHeight <= 0) {
			Log::warning("Invalid aspect ratio values", [
				'ratio' => $this->ratio,
				'width' => $ratioWidth,
				'height' => $ratioHeight
			]);
			return $image;
		}
		
		// Calculate target aspect ratio as a decimal
		$targetRatio = $ratioWidth / $ratioHeight;
		
		// Calculate current aspect ratio
		$currentRatio = $origWidth / $origHeight;
		
		// Determine crop dimensions
		$cropX = 0;
		$cropY = 0;
		$cropWidth = $origWidth;
		$cropHeight = $origHeight;
		
		// If current ratio is wider than target ratio, crop width
		if ($currentRatio > $targetRatio) {
			// Need to crop width to match target ratio
			$cropWidth = round($origHeight * $targetRatio);
			// Center the crop horizontally
			$cropX = round(($origWidth - $cropWidth) / 2);
		} 
		// If current ratio is taller than target ratio, crop height
		elseif ($currentRatio < $targetRatio) {
			// Need to crop height to match target ratio
			$cropHeight = round($origWidth / $targetRatio);
			// Center the crop vertically
			$cropY = round(($origHeight - $cropHeight) / 2);
		}
		// If ratios match, no crop needed
		
		// Log the planned crop
		Log::debug("Cropping image to ratio {$this->ratio}", [
			'original_width' => $origWidth,
			'original_height' => $origHeight,
			'crop_x' => $cropX,
			'crop_y' => $cropY,
			'crop_width' => $cropWidth,
			'crop_height' => $cropHeight
		]);
		
		// Crop the image
		try {
			$image = $image->crop($cropWidth, $cropHeight, $cropX, $cropY);
			
			// Update orientation based on the cropped image
			$this->updateOrientation($image);
		} catch (\Exception $e) {
			Log::error("Error during ratio crop operation: " . $e->getMessage(), [
				'ratio' => $this->ratio,
				'exception' => $e
			]);
			// Continue with the original image if cropping fails
		}
		
		return $image;
	}
	
	/**
	 * Scale down the image based on orientation and max dimensions
	 *
	 * @param ImageInterface $image The image to scale
	 * @return ImageInterface The scaled image
	 */
	protected function scaleImage(ImageInterface $image): ImageInterface
	{
		// If maxSize is provided, it should constrain the largest dimension
		if ($this->maxSize) {
			$width = $image->width();
			$height = $image->height();
			
			// Determine which dimension is larger and constrain it
			if ($width >= $height) {
				return $image->scaleDown(width: $this->maxSize);
			} else {
				return $image->scaleDown(height: $this->maxSize);
			}
		}
		
		// If specific dimensions are provided, use them based on orientation
		if ($this->orientation === 'landscape') {
			if ($this->maxWidth) {
				return $image->scaleDown(width: $this->maxWidth);
			}
		} else { // portrait
			if ($this->maxHeight) {
				return $image->scaleDown(height: $this->maxHeight);
			}
		}
		
		// If no resizing parameters were provided, return the image as is
		return $image;
	}
}