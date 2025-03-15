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
	protected ?int $maxSize = 1600;

	/**
	 * Maximum width for the image
	 */
	protected ?int $maxWidth = 1600;

	/**
	 * Maximum height for the image
	 */
	protected ?int $maxHeight = 1200;

	/**
	 * Coordinates for cropping (x,y,width,height)
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
	 * @param string|null $coords Coordinates for cropping (x,y,width,height)
	 * @param string|null $ratio Aspect ratio (width x height)
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
		
		// First crop the image if coordinates are provided
		if ($this->coords && $this->coords != '0,0,0,0') {
			$image = $this->cropImage($image);
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
		// This method should only be called when coordinates are valid
		// But we'll keep a check just in case
		
		// Parse coordinates in x,y,width,height format
		list($cropX, $cropY, $cropWidth, $cropHeight) = explode(',', $this->coords);
		
		// Convert to integer values
		$cropX = (int)$cropX;
		$cropY = (int)$cropY;
		$cropWidth = (int)$cropWidth;
		$cropHeight = (int)$cropHeight;
		
		// Ensure crop dimensions are valid
		if ($cropWidth <= 0) $cropWidth = 1;
		if ($cropHeight <= 0) $cropHeight = 1;
		
		// Ensure crop area is within image boundaries
		$width = $image->width();
		$height = $image->height();
		
		if ($cropX + $cropWidth > $width) $cropWidth = $width - $cropX;
		if ($cropY + $cropHeight > $height) $cropHeight = $height - $cropY;
		
		// Log the coordinates for debugging
		Log::debug("Cropping image with coordinates", [
			'x' => $cropX,
			'y' => $cropY,
			'width' => $cropWidth,
			'height' => $cropHeight
		]);
		
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
	 * Scale down the image based on orientation and max dimensions
	 *
	 * @param ImageInterface $image The image to scale
	 * @return ImageInterface The scaled image
	 */
	protected function scaleImage(ImageInterface $image): ImageInterface
	{
		if ($this->orientation === 'landscape') {
			if ($this->maxWidth) {
				return $image->scaleDown(width: $this->maxWidth);
			} elseif ($this->maxSize) {
				return $image->scaleDown(width: $this->maxSize);
			}
		} else { // portrait
			if ($this->maxHeight) {
				return $image->scaleDown(height: $this->maxHeight);
			} elseif ($this->maxSize) {
				return $image->scaleDown(height: $this->maxSize);
			}
		}
		
		// If no resizing parameters were provided, return the image as is
		return $image;
	}
}