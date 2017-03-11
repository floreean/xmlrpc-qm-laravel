<?php

namespace floreean\XmlQmLaravel;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class QueueManagerServiceProvider extends ServiceProvider {

  public function boot()
  {
    // will copy the default configuration in the laravel project
    $this->publishes( [ __DIR__ . '/config/queuemanager.php' => config_path( 'queuemanager.php' ) ] );
  }

  /**
   * Register the service provider.
   *
   * @return void
   */
  public function register()
  {

    // See src/Facades/QueueManagerLaravelFacade.php
    App::bind( 'floreean.queuemanager.laravel.facade', function () {

      // Feel free to comment this section if you no need of configuration
      $config = config( 'queuemanager' );

      if ( ! $config ) {
        throw new \RunTimeException( 'QueueManager Facade configuration not found. Please run `php artisan vendor:publish`' );
      }

      // DO NOT REMOVE or COMMENT - here we'll create the Facade
      // Remove $config if you have comment the lines above
      return new \floreean\XmlQmLaravel\QueueManager( $config );
    } );
  }
}