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
		try {
			// Get image dimensions
			$width = $image->width();
			$height = $image->height();

			// Determine orientation
			$this->orientation = $height > $width ? 'portrait' : 'landscape';

			// If coordinates are provided, crop the image
			if ($this->coords && $this->coords != '0,0,0,0') {
				// Parse coordinates in x,y,width,height format
				$parts = explode(',', $this->coords);

				// Ensure we have 4 parts
				if (count($parts) !== 4) {
					// Log warning and return original image
					error_log("Invalid crop coordinates format: {$this->coords}. Expected format: x,y,width,height");
					return $image;
				}

				// Convert to float values
				$cropX = (float) $parts[0];
				$cropY = (float) $parts[1];
				$cropWidth = (float) $parts[2];
				$cropHeight = (float) $parts[3];

				// Validate crop dimensions - ensure they're positive values
				if ($cropWidth <= 0 || $cropHeight <= 0) {
					error_log("Invalid crop dimensions: width={$cropWidth}, height={$cropHeight}. Using minimum dimensions.");

					// If dimensions are invalid, use a small portion of the image instead of trying to crop
					// This avoids the imagecreatetruecolor() error
					return $this->scaleImage($image);
				}

				// Ensure crop area is within image boundaries
				if ($cropX >= $width || $cropY >= $height) {
					error_log("Crop position outside image boundaries: x={$cropX}, y={$cropY}, image_width={$width}, image_height={$height}");
					return $this->scaleImage($image);
				}

				// Adjust crop dimensions if they exceed image boundaries
				if ($cropX + $cropWidth > $width) {
					$cropWidth = $width - $cropX;
				}

				if ($cropY + $cropHeight > $height) {
					$cropHeight = $height - $cropY;
				}

				// Final safety check
				if ($cropWidth <= 0 || $cropHeight <= 0) {
					error_log("Adjusted crop dimensions are still invalid: width={$cropWidth}, height={$cropHeight}");
					return $this->scaleImage($image);
				}

				// Update orientation based on crop dimensions
				$this->orientation = $cropHeight > $cropWidth ? 'portrait' : 'landscape';

				// Crop the image
				try {
					$image = $image->crop(
						floor($cropWidth),
						floor($cropHeight),
						floor($cropX),
						floor($cropY)
					);
				} catch (\Exception $e) {
					error_log("Error cropping image: " . $e->getMessage());
					// If cropping fails, return the original image with scaling
					return $this->scaleImage($image);
				}
			}

			// If ratio is provided, adjust the image dimensions
			if ($this->ratio) {
				try {
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
				} catch (\Exception $e) {
					error_log("Error applying ratio: " . $e->getMessage());
					// If ratio adjustment fails, continue with the current image
				}
			}

			// Scale the image based on orientation and max dimensions
			return $this->scaleImage($image);
		} catch (\Exception $e) {
			error_log("Unexpected error in Crop template: " . $e->getMessage());
			// Return the original image if any unexpected error occurs
			return $image;
		}
	}

	/**
	 * Scale the image based on orientation and max dimensions
	 *
	 * @param ImageInterface $image The image to scale
	 * @return ImageInterface The scaled image
	 */
	private function scaleImage(ImageInterface $image): ImageInterface
	{
		try {
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
		} catch (\Exception $e) {
			error_log("Error scaling image: " . $e->getMessage());
		}

		// If no resizing parameters were provided or scaling failed, return the image as is
		return $image;
	}
}
