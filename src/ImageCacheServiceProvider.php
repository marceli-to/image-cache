<?php

namespace MarceliTo\ImageCache;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class ImageCacheServiceProvider extends ServiceProvider
{
	/**
	 * Register services.
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/image-cache.php', 'image-cache'
		);

		$this->app->singleton('image-cache', function ($app) {
			return new ImageCache();
		});
	}

	/**
	 * Bootstrap services.
	 */
	public function boot(): void
	{
		// Publish configuration
		$this->publishes([
			__DIR__ . '/../config/image-cache.php' => config_path('image-cache.php'),
		], 'image-cache-config');

		// Register commands
		if ($this->app->runningInConsole()) {
			$this->commands([
				Commands\ClearImageCacheCommand::class,
			]);
		}
		
		// Register routes
		if (config('image-cache.register_routes', true)) {
			$this->registerRoutes();
		}
	}
	
	/**
	 * Register the package routes.
	 */
	protected function registerRoutes(): void
	{
		Route::group($this->routeConfiguration(), function () {
			// Standard template routes
			Route::get('/img/{template}/{filename}', 
				[\MarceliTo\ImageCache\Http\Controllers\ImageController::class, 'getResponse'])
				->name('image-cache.image')
				->where('template', '(?!crop).*'); // Exclude 'crop' from this route
				
			// Register crop routes based on configuration
			if (config('image-cache.crop_filter_type', 'maxSize') === 'maxSize') {
				// Crop route with maxSize
				Route::get('/img/crop/{filename}/{maxSize?}/{coords?}/{ratio?}', 
					[\MarceliTo\ImageCache\Http\Controllers\ImageController::class, 'getCropResponse'])
					->name('image-cache.crop');
			} else {
				// Crop route with maxWidth and maxHeight
				Route::get('/img/crop/{filename}/{maxWidth?}/{maxHeight?}/{coords?}/{ratio?}', 
					[\MarceliTo\ImageCache\Http\Controllers\ImageController::class, 'getCropWithDimensionsResponse'])
					->name('image-cache.crop');
			}
		});
	}
	
	/**
	 * Get the route group configuration.
	 * 
	 * @return array
	 */
	protected function routeConfiguration(): array
	{
		return [
			'middleware' => config('image-cache.middleware', ['web']),
		];
	}
}
