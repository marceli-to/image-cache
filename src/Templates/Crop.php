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
		// Get image dimensions
		$width = $image->width();
		$height = $image->height();

		// Determine orientation
		$this->orientation = $height > $width ? 'portrait' : 'landscape';

		// If coordinates are provided, crop the image
		if ($this->coords && $this->coords != '0,0,0,0') {
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
				$this->orientation = $image->height() > $image->width() ? 'portrait' : 'landscape';
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
		}
		
		// Scale down the image based on orientation and max dimensions
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
