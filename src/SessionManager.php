<?php

namespace ReactDrupal;

use \Drupal\Core\Session\SessionManager as DrupalSessionManager;

class SessionManager extends DrupalSessionManager {

  public function setOptions(array $options) {
    if (\PHP_SESSION_ACTIVE === session_status()) {
      return;
    }

    $validOptions = array_flip([
      'cache_expire', 'cache_limiter', 'cookie_domain', 'cookie_httponly',
      'cookie_lifetime', 'cookie_path', 'cookie_secure', 'cookie_samesite',
      'gc_divisor', 'gc_maxlifetime', 'gc_probability',
      'lazy_write', 'name', 'referer_check',
      'serialize_handler', 'use_strict_mode', 'use_cookies',
      'use_only_cookies', 'use_trans_sid',
      'sid_length', 'sid_bits_per_character', 'trans_sid_hosts', 'trans_sid_tags',
    ]);

    foreach ($options as $key => $value) {
      if (isset($validOptions[$key])) {
        if ('cookie_secure' === $key && 'auto' === $value) {
          continue;
        }
        ini_set('session.'.$key, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function start(): bool {
    $request = $this->requestStack->getCurrentRequest();
    $this->setOptions($this->sessionConfiguration->getOptions($request));

    if ($this->sessionConfiguration->hasSession($request)) {
      // If a session cookie exists, initialize the session. Otherwise the
      // session is only started on demand in save(), making
      // anonymous users not use a session cookie unless something is stored in
      // $_SESSION. This allows HTTP proxies to cache anonymous page views.
      $result = $this->startNow();
    }

    if (empty($result)) {
      // Initialize the session global and attach the Symfony session bags.
      $_SESSION = [];
      $this->loadSession();

      // NativeSessionStorage::loadSession() sets started to TRUE, reset it to
      // FALSE here.
      $this->started = FALSE;
      $this->startedLazy = TRUE;

      $result = FALSE;
    }

    return $result;
  }

}
