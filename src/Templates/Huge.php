<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

class Huge implements ModifierInterface
{
	/**
	 * Maximum width for huge landscape images
	 */    
	protected $max_width = 3000;    

	/**
	 * Maximum height for huge portrait images
	 */    
	protected $max_height = 1688;
	
	/**
	 * Apply filter to image
	 */
	public function apply(ImageInterface $image): ImageInterface
	{
		// Get image orientation
		$orientation = $image->height() > $image->width() ? 'portrait' : 'landscape';
		
		// Scale down the image based on orientation while maintaining aspect ratio
		if ($orientation === 'landscape')
		{
			return $image->scaleDown(width: $this->max_width);
		} 
		else
		{
			return $image->scaleDown(height: $this->max_height);
		}
	}
}
