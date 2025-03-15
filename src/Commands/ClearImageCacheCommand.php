<?php

namespace MarceliTo\ImageCache\Commands;

use MarceliTo\ImageCache\ImageCache;
use Illuminate\Console\Command;

class ClearImageCacheCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'image:clear-cache {template? : The template to clear cache for}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Clear the image cache';

	/**
	 * Execute the console command.
	 */
	public function handle(ImageCache $imageCache)
	{
		$template = $this->argument('template');

		if ($template) {
			$result = $imageCache->clearTemplateCache($template);
			$this->info("Image cache for template '{$template}' cleared successfully.");
		} else {
			$result = $imageCache->clearCache();
			$this->info('All image cache cleared successfully.');
		}

		return $result ? Command::SUCCESS : Command::FAILURE;
	}
}
