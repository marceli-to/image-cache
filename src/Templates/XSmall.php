<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

class XSmall implements ModifierInterface
{
	/**
	 * Maximum size for the image
	 */
	protected $maxSize = 240;

	/**
	 * Apply filter to image
	 *
	 * @param ImageInterface $image The image to modify
	 * @return ImageInterface The modified image
	 */
	public function apply(ImageInterface $image): ImageInterface
	{
		// Get image orientation
		$orientation = $image->height() > $image->width() ? 'portrait' : 'landscape';
		
		// Scale down the image based on orientation
		if ($orientation === 'landscape')
		{
			return $image->scaleDown(width: $this->maxSize);
		} 
		else
		{
			return $image->scaleDown(height: $this->maxSize);
		}
	}
}
