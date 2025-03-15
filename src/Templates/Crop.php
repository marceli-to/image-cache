<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

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
			
			// Convert to float values
			$cropX = (float)$cropX;
			$cropY = (float)$cropY;
			$cropWidth = (float)$cropWidth;
			$cropHeight = (float)$cropHeight;
			
			// Validate crop dimensions - ensure they're positive values
			if ($cropWidth <= 0) {
				$cropWidth = 1; // Set a minimum width of 1 pixel
			}
			if ($cropHeight <= 0) {
				$cropHeight = 1; // Set a minimum height of 1 pixel
			}
			
			// Ensure crop area is within image boundaries
			if ($cropX + $cropWidth > $width) {
				$cropWidth = $width - $cropX;
			}
			if ($cropY + $cropHeight > $height) {
				$cropHeight = $height - $cropY;
			}
			
			// Update orientation based on crop dimensions
			$this->orientation = $cropHeight > $cropWidth ? 'portrait' : 'landscape';
			
			// Crop the image
			$image = $image->crop(
				floor($cropWidth), 
				floor($cropHeight), 
				floor($cropX), 
				floor($cropY)
			);
		}

		// If ratio is provided, adjust the image dimensions
		if ($this->ratio) {
			// Parse the ratio (format: width x height)
			// Support both 'x' and ':' as separators for backward compatibility
			$separator = strpos($this->ratio, 'x') !== false ? 'x' : ':';
			list($ratioWidth, $ratioHeight) = explode($separator, $this->ratio);
			$ratioWidth = (float) $ratioWidth;
			$ratioHeight = (float) $ratioHeight;
			
			if ($ratioWidth > 0 && $ratioHeight > 0) {
				$targetRatio = $ratioWidth / $ratioHeight;
				$currentRatio = $image->width() / $image->height();
				
				// If current ratio doesn't match target ratio, crop to fit
				if (abs($currentRatio - $targetRatio) > 0.01) {
					if ($currentRatio > $targetRatio) {
						// Image is too wide, crop width
						$newWidth = $image->height() * $targetRatio;
						$cropX = ($image->width() - $newWidth) / 2;
						$image = $image->crop(
							floor($newWidth),
							$image->height(),
							floor($cropX),
							0
						);
					} else {
						// Image is too tall, crop height
						$newHeight = $image->width() / $targetRatio;
						$cropY = ($image->height() - $newHeight) / 2;
						$image = $image->crop(
							$image->width(),
							floor($newHeight),
							0,
							floor($cropY)
						);
					}
					
					// Update orientation based on the new ratio
					$this->orientation = $ratioHeight > $ratioWidth ? 'portrait' : 'landscape';
				}
			}
		}

		// Scale down the image based on orientation and max dimensions
		if ($this->orientation === 'landscape') {
			if ($this->maxWidth) {
				return $image->scaleDown(width: $this->maxWidth);
			} elseif ($this->maxSize) {
				return $image->scaleDown(width: $this->maxSize);
			}
		} else {
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
