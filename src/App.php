<?php

namespace ReactDrupal;

use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
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
    $this->kernel::bootEnvironment();
    $this->kernel->setSitePath('sites/default');
    Settings::initialize($this->kernel->getAppRoot(), $this->kernel->getSitePath(), $this->classLoader);
    $this->kernel->boot();
    chdir($this->kernel->getAppRoot());

    return $this;
  }

  public function runHttpServer(): void {
    $http = new HttpServer(function (ServerRequestInterface $serverRequest) {
      $request = $this->httpFoundationFactory->createRequest($serverRequest);
      $this->kernel->getContainer()
        ->get('request_stack')
        ->push($request);
      // This sets things up, esp loadLegacyIncludes().
      $this->kernel->preHandle($request);

      $response = $this->kernel->getContainer()
        ->get('http_kernel')
        ->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE);

      return $this->psrHttpFactory->createResponse($response);
    });

    $socket = new SocketServer('0.0.0.0:8080');
    $http->listen($socket);

    $http->on('error', function (\Exception $e) {
      echo 'Error: ' . $e->getMessage() . PHP_EOL;
    });

    $socket->on('error', function (\Exception $e) {
      echo 'Error: ' . $e->getMessage() . PHP_EOL;
    });

    echo "Server running at http://0.0.0.0:8080" . PHP_EOL;
  }

}
