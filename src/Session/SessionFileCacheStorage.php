<?php

namespace ReactDrupal\Session;

use Psr\SimpleCache\CacheInterface;

class SessionFileCacheStorage implements CacheInterface {

  protected array $data = [];

  public function __construct(
    protected string $cachePath,
    protected string $cacheName,
  ) {}

  protected function getData(): array {
    $data = file_get_contents($this->getCacheFilePath());
    $this->data = $data ? unserialize($data) : [];

    return $this->data;
  }

  protected function saveData(array $data): bool {
    if (!is_dir($this->cachePath) && !mkdir($this->cachePath) && !is_dir($this->cachePath)) {
      throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->cachePath));
    }

    $this->data = $data;

    return file_put_contents($this->getCacheFilePath(), serialize($data));
  }

  public function get(string $key, mixed $default = NULL): mixed {
    return array_key_exists($key, $this->getData()) ? $this->getData()[$key] : $default;
  }

  public function set(string $key, mixed $value, null|int|\DateInterval $ttl = NULL): bool {
    $data = $this->getData();
    $data[$key] = $value;

    return $this->saveData($data);
  }

  public function delete(string $key): bool {
    $data = $this->getData();
    unset($data[$key]);

    return $this->saveData($data);
  }

  public function getMultiple(iterable $keys, mixed $default = NULL): iterable {
    $result = [];
    foreach ($keys as $key) {
      $result[$key] = $this->get($key, $default);
    }

    return $result;
  }

  public function setMultiple(iterable $values, null|int|\DateInterval $ttl = NULL): bool {
    $data = $this->getData();

    if (is_array($values)) {
      $mergedValues = $values;
    }
    elseif ($values instanceof \Traversable) {
      $mergedValues = iterator_to_array($values);
    }
    else {
      throw new \Exception('Argument $values must be iterable');
    }

    return $this->saveData(array_merge($data, $mergedValues));
  }

  public function deleteMultiple(iterable $keys): bool {
    $data = $this->getData();

    foreach ($keys as $key) {
      unset($data[$key]);
    }

    return $this->saveData($data);
  }

  public function clear(): bool {
    $this->data = [];

    $filePath = $this->getCacheFilePath();
    if (file_exists($filePath)) {
      return unlink($filePath);
    }

    return TRUE;
  }

  public function has(string $key): bool {
    return array_key_exists($key, $this->getData());
  }

  private function getCacheFilePath(): string {
    return rtrim($this->cachePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->cacheName . '.php';
  }

}
