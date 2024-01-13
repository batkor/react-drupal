<?php

namespace ReactDrupal\ServiceProvider;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use ReactDrupal\SessionManager;

class ReactDrupalServiceProvider implements ServiceModifierInterface {

  public function alter(ContainerBuilder $container) {
    $container->getDefinition('session_manager')->setClass(SessionManager::class);
  }

}
