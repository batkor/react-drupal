<?php

namespace ReactDrupal\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Commands\DrushCommands;
use ReactDrupal\ServiceProvider\ReactDrupalServiceProvider;

class ReactDrupalAlterDrushCommands extends DrushCommands {

  /**
   * @hook init cache:rebuild
   */
  public function initCacheRebuild(): void {
    $GLOBALS['conf']['container_service_providers'][] = ReactDrupalServiceProvider::class;
  }

}
