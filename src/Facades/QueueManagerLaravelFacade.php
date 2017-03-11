<?php

namespace floreean\XmlQmLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class QueueManagerLaravelFacade extends Facade {

  /**
   * Get the registered name of the component.
   * See src/QueueManagerLaravelServiceProvider.php
   *
   * @return string
   */
  protected static function getFacadeAccessor()
  {
    return 'floreean.queuemanager.laravel.facade';
  }

}