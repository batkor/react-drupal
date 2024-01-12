<?php

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$autoloader = file_exists(__DIR__ . '/../vendor/autoload.php')
  ? require __DIR__ . '/../vendor/autoload.php'
  : require __DIR__ . '/../../../autoload.php';

$httpFoundationFactory = new HttpFoundationFactory();
$kernel = new DrupalKernel('prod', $autoloader, FALSE);
$kernel::bootEnvironment();
$kernel->setSitePath('sites/default');
Settings::initialize($kernel->getAppRoot(), $kernel->getSitePath(), $autoloader);
$kernel->boot();
chdir($kernel->getAppRoot());

$psr17Factory = new Psr17Factory();
$psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

$http = new HttpServer(function (ServerRequestInterface $serverRequest) use ($autoloader, $httpFoundationFactory, $kernel, $psrHttpFactory) {
  $request = $httpFoundationFactory->createRequest($serverRequest);
  $kernel->getContainer()
    ->get('request_stack')
    ->push($request);
  // This sets things up, esp loadLegacyIncludes().
  $kernel->preHandle($request);

  $response = $kernel->getContainer()
    ->get('http_kernel')
    ->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE);

  return $psrHttpFactory->createResponse($response);
});

$socket = new SocketServer('0.0.0.0:8080');
$http->listen($socket);

$http->on('error', function (Exception $e) {
  echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$socket->on('error', function (Exception $e) {
  echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

echo "Server running at http://0.0.0.0:8080" . PHP_EOL;
