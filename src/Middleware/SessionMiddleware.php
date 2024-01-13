<?php

namespace ReactDrupal\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Cache\CacheInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class SessionMiddleware {

  /**
   * The session storage.
   */
  protected CacheInterface $cache;

  public function __construct(CacheInterface $cache) {
    $this->cache = $cache;
  }

  public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface {
    flush();
    ob_clean();
    return resolve($next($request));
  }

}
