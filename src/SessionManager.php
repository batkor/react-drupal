<?php

namespace ReactDrupal;

use Compwright\PhpSession\Factory;
use Compwright\PhpSession\Manager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Session\WriteSafeSessionHandlerInterface;
use ReactDrupal\Session\SessionFileCacheStorage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Drupal\Core\Session\MetadataBag as DrupalMetadataBag;

class SessionManager implements SessionManagerInterface {

  protected Manager $manager;

  /**
   * The write safe session handler.
   */
  protected WriteSafeSessionHandlerInterface $writeSafeHandler;

  /**
   * @var \Symfony\Component\HttpFoundation\Session\SessionBagInterface[]
   */
  protected array $bags;

  public function __construct(
    protected RequestStack $requestStack,
    protected Connection $connection,
    protected SessionConfigurationInterface $sessionConfiguration,
    protected DrupalMetadataBag $metadataBag
  ) {
    $sessionFactory = new Factory();
    $this->manager = $sessionFactory
      ->psr16Session(new SessionFileCacheStorage(sys_get_temp_dir(), 'react-drupal'), [
        'name' => 'react_drupal',
        'sid_length' => 48,
        'sid_bits_per_character' => 6,
      ]);
  }

  public function delete($uid) {
    $this->connection->delete('sessions')
      ->condition('uid', $uid)
      ->execute();
  }

  public function destroy() {
    $this->manager->destroy();
  }

  public function setWriteSafeHandler(WriteSafeSessionHandlerInterface $handler): void {
    $this->writeSafeHandler = $handler;
  }

  public function start(): bool {
    if ($this->sessionConfiguration->hasSession($this->requestStack->getCurrentRequest())) {
      $this->manager->id($this->getName());
      $this->manager->start();

      return TRUE;
    }

    return FALSE;
  }

  public function isStarted(): bool {
    return !!$this->manager->status();
  }

  public function getId(): string {
    return $this->manager->id($this->getName());
  }

  public function setId(string $id) {
    $this->manager->id($id);
  }

  public function getName(): string {
    $options = $this->sessionConfiguration->getOptions($this->requestStack->getCurrentRequest());

    return $options['name'];
  }

  public function setName(string $name) {
    $x=0;
  }

  public function regenerate(bool $destroy = FALSE, int $lifetime = NULL): bool {
    if (!$this->isStarted()) {
      $this->start();
    }

    if ($destroy) {
      $this->manager->regenerate_id(TRUE);
    }

    return TRUE;
  }

  public function save() {
    $this->manager->write_close();
  }

  public function clear() {

  }

  public function getBag(string $name): SessionBagInterface {
    return $this->bags[$name];
  }

  public function registerBag(SessionBagInterface $bag): void {
    $this->bags[$bag->getName()] = $bag;
  }

  public function getMetadataBag(): MetadataBag {
    return $this->metadataBag;
  }


}
