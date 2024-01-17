<?php

namespace ReactDrupal;

use React\Cache\ArrayCache;
use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use ReactDrupal\Middleware\ReactDrupalMiddleware;
use ReactDrupal\ServiceProvider\ReactDrupalServiceProvider;
use ReactDrupal\Session\SessionFileCacheStorage;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The app for run Drupal using reactPHP.
 */
class App {

  /**
   * The PSR http foundation factory.
   */
  protected HttpFoundationFactory $httpFoundationFactory;

  /**
   * The Drupal kernel.
   */
  protected DrupalKernel $kernel;

  /**
   * The PRS HTTP factory.
   */
  protected PsrHttpFactory $psrHttpFactory;

  /**
   * The class loader.
   */
  protected ClassLoader $classLoader;

  public function __construct(ClassLoader $classLoader, HttpFoundationFactory $httpFoundationFactory, DrupalKernel $kernel, PsrHttpFactory $psrHttpFactory) {
    $this->httpFoundationFactory = $httpFoundationFactory;
    $this->kernel = $kernel;
    $this->psrHttpFactory = $psrHttpFactory;
    $this->classLoader = $classLoader;
  }

  public static function create(ClassLoader $classLoader): static {
    $psr17Factory = new Psr17Factory();

    return new static($classLoader, new HttpFoundationFactory(), new DrupalKernel('prod', $classLoader, FALSE), new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory));
  }

  public function initKernel(): self {
    $GLOBALS['conf']['container_service_providers'][] = ReactDrupalServiceProvider::class;

    chdir($this->kernel->getAppRoot());
    $this->kernel::bootEnvironment();
    $this->kernel->setSitePath('sites/default');
    Settings::initialize($this->kernel->getAppRoot(), $this->kernel->getSitePath(), $this->classLoader);
    $this->kernel->boot();

    return $this;
  }

  public function runHttpServer(): void {
    $http = new HttpServer(
//      new SessionMiddleware(new SessionFileCacheStorage(sys_get_temp_dir(), 'react-drupal')),
      function (ServerRequestInterface $serverRequest) {
        try {
          $request = $this->httpFoundationFactory->createRequest($serverRequest);
          $request->attributes->set('server_request', $serverRequest);
          // This sets things up, esp loadLegacyIncludes().
          $this->kernel->preHandle($request);

          $response = $this->kernel->getContainer()
            ->get('http_kernel')
            ->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE);
          $response->prepare($request);
        }
        catch (\Exception $e) {
          if ($e instanceof HttpExceptionInterface) {
            $response = new Response($e->getMessage(), $e->getStatusCode());
            $response->headers->add($e->getHeaders());
          }
        }
        finally {
          // @todo Use|move to finally promise or end event.
          $this->kernel->terminate($request, $response);

          return $this->psrHttpFactory->createResponse($response);
        }
      },
    );

    $socket = new SocketServer('0.0.0.0:8080');
    $http->listen($socket);

    $http->on('error', function (\Exception $e) {
      echo '$http Error: ' . $e->getMessage() . PHP_EOL;
    });

    $socket->on('error', function (\Exception $e) {
      echo '$socket Error: ' . $e->getMessage() . PHP_EOL;
    });

    echo "Server running at http://0.0.0.0:8080" . PHP_EOL;
  }

}
