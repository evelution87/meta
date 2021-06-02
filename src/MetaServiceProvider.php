<?php

namespace Evelution\Meta;

use Illuminate\Support\ServiceProvider;

class MetaServiceProvider extends ServiceProvider {
	/**
	 * Bootstrap the application services.
	 */
	public function boot() {
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
	}
	
	/**
	 * Register the application services.
	 */
	public function register() {
		// Register the main class to use with the facade
		$this->app->singleton( 'meta', function() {
			return new Meta;
		} );
	}
}
