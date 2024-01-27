<?php

namespace ReactDrupal;

use Compwright\PhpSession\Factory;
use Compwright\PhpSession\Manager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Session\WriteSafeSessionHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag as SymfonyMetadataBag;
use ReactDrupal\Session\SessionFileCacheStorage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

class SessionManager implements SessionManagerInterface {

  protected Manager $manager;

  /**
   * The bags list.
   */
  protected array $bags = [];

  /**
   * The session started status.
   */
  protected bool $started = false;

  /**
   * The write safe session handler.
   *
   * @todo: This reference should be removed once all database queries
   *   are removed from the session manager class.
   */
  protected WriteSafeSessionHandlerInterface $writeSafeHandler;

  public function __construct(
    protected RequestStack $requestStack,
    protected Connection $connection,
    protected MetadataBag $metadataBag,
    protected SessionConfigurationInterface $sessionConfiguration,
  ) {
    $sessionFactory = new Factory();
    $options = $this->sessionConfiguration->getOptions($requestStack->getCurrentRequest());
    $this->manager = $sessionFactory
      ->psr16Session(new SessionFileCacheStorage('/tmp', $options['name']), $options);
  }

  /**
   * {@inheritdoc}
   */
  public function start(): bool {
    if ($this->manager->status() === \PHP_SESSION_ACTIVE && $this->manager->getCurrentSession()->isWriteable()) {
      return true;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($this->sessionConfiguration->hasSession($request)) {
      $this->setId($request->cookies->get($this->getName()));
    }
    else {
      $this->setId(md5(\microtime()));
    }

    $this->started = $this->manager->start();

    if ($this->started) {
      $session = $this->manager->getCurrentSession();

      if (!empty($session->count())) {
        $this->bags = $session->toArray();
      }
    }

    return $this->started;
  }

  protected function isCli() {
    return !$this->requestStack->getCurrentRequest()->attributes->has('server_request') && \PHP_SAPI === 'cli';
  }

  public function getManager() {
    return $this->manager;
  }

  public function isStarted(): bool {
    return  $this->started;
  }

  public function getId(): string {
    return $this->getManager()->id();
  }

  public function setId(string $id) {
    $this->manager->id($id);
  }

  public function getName(): string {
    return $this->manager->getConfig()->getName();
  }

  public function setName(string $name) {
    return $this->manager->getConfig()->setName($name);
  }

  public function regenerate(bool $destroy = FALSE, int $lifetime = NULL): bool {
    return $this->manager->regenerate_id($destroy);
  }

  public function save() {
    $session = $this->manager->getCurrentSession();

    if ($session) {
      $session->setContents($this->bags);
    }

    $this->manager->write_close();
  }

  public function clear() {
    $this->manager->unset();
  }

  public function getBag(string $name): SessionBagInterface {
    if (!isset($this->bags[$name])) {
      throw new \InvalidArgumentException(sprintf('The SessionBagInterface "%s" is not registered.', $name));
    }

    if (!$this->isStarted()) {
      $this->start();
    }

    return $this->bags[$name];
  }

  public function registerBag(SessionBagInterface $bag) {
    if ($this->isStarted()) {
      throw new \LogicException('Cannot register a bag when the session is already started.');
    }

    $this->bags[$bag->getName()] = $bag;
  }

  public function getMetadataBag(): SymfonyMetadataBag {
    return $this->metadataBag;
  }

  public function delete($uid) {
    $this->connection->delete('sessions')
      ->condition('uid', $uid)
      ->execute();
  }

  public function destroy(): void {
    $this->manager->destroy();
    $this->manager->getCurrentSession()->setContents([]);
    $this->bags = [];
  }

  public function buildCookie(): Cookie {
    $config = $this->getManager()->getConfig();
    return new Cookie(
      $this->getName(),
      $this->getId(),
      $config->getCookieLifetime() + time(),
      $config->getCookiePath(),
      $config->getCookieDomain(),
      $config->getCookieSecure(),
      $config->getCookieHttpOnly(),
      TRUE,
      $config->getCookieSameSite(),
    );
  }

  public function setWriteSafeHandler(WriteSafeSessionHandlerInterface $handler) {
    $this->writeSafeHandler = $handler;
  }

}
