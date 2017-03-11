<?php

namespace floreean\XmlQmLaravel;

/**
 * QueueManagerLaravel class.
 *
 * @class           QueueManager
 * @author          <irlesberger@gmail.com>
 * @date            2017-03-11
 * @version         1.0.0
 *
 * @history
 *
 *
 */
class QueueManager
{

  public function __construct( $config = [ ] )
  {
    // You may comment this line if you application doesn't support the config
    if ( empty( $config ) ) {
      throw new \RunTimeException( 'QueueManager Facade configuration is empty. Please run `php artisan vendor:publish`' );
    }
  }

  // This methods will be available by QueueManagerLaravel::helloWorld()
  public function helloWorld()
  {
    return 'Hello, World!';
  }
}


/*
|--------------------------------------------------------------------------
| QueueManager Exceptions
|--------------------------------------------------------------------------
|
| These exceptions classes are used in this file. Feel free to add your
| custom feedback and classes.
|
*/

class QueueManagerException extends \Exception{}