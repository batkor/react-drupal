<?php

namespace ReactDrupal\ServiceProvider;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use ReactDrupal\Middleware\ReactDrupalMiddleware;
use ReactDrupal\SessionManager;
use Symfony\Component\DependencyInjection\Reference;

class ReactDrupalServiceProvider implements ServiceModifierInterface {

  public function alter(ContainerBuilder $container) {
//    $container
//      ->getDefinition('session_manager')
//      ->setClass(SessionManager::class)
//      ->setArguments([
//        new Reference('request_stack'),
//        new Reference('database'),
//        new Reference('session_configuration'),
//        new Reference('session_manager.metadata_bag'),
//      ]);

    $container
      ->getDefinition('http_middleware.session')
      ->setClass(ReactDrupalMiddleware::class);
  }

}
