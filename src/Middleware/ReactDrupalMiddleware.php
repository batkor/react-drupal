<?php

namespace ReactDrupal\Middleware;

use Drupal\Core\StackMiddleware\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReactDrupalMiddleware extends Session {

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    if ($type === self::MAIN_REQUEST && (PHP_SAPI !== 'cli' || $request->attributes->has('server_request'))) {
      $session = $this->container->get($this->sessionServiceName);
      $session->start();
      $request->setSession($session);
    }

    $result = $this->httpKernel->handle($request, $type, $catch);

    if ($type === self::MAIN_REQUEST && $request->hasSession()) {
      $request->getSession()->save();
    }

    return $result;
  }

}
