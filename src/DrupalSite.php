<?php

namespace Drupal\drmanage;

class DrupalSite {
  private $node = null;

  public function __construct($node=null)
  {
    if ($node) {
      $this->node = $node;
    }
  }

  public static function all(bool $active=true)
  {
    $nids = \Drupal::entityQuery('node')
    ->condition('type', 'drupal_site')
    ->condition('field_active_site', $active)
    ->execute();

    $sites = [];

    foreach ($nids as $nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      $sites[] = new self($node);
    }

    return $sites;
  }

  public function backup()
  {
    if ($this->node) {
      if ($result = $this->run_agent($this->get_host_url() . "/manage.php?operation=backup&verbose=true")) {
        $json = json_decode($result['data']);
        if (isset($json->messages)) {
          $this->update_backup_log(join("\n", $json->messages));
        }
        if (isset($json->status) && $json->status == 'success') {
          $this->update_event_time('backup');
        }
        return [
          'bytes' => $result['bytes'],
          'json' => $json,
        ];
      }
    }
    return [
      'bytes' => 0,
      'json' => [],
    ];
  }

  public function restore()
  {
    if ($this->node) {
      if ($result = $this->run_agent($this->get_host_url() . "/manage.php?operation=restore&verbose=true")) {
        $json = json_decode($result['data']);
        $this->update_event_time('restore');
        return [
          'bytes' => $result['bytes'],
          'json' => $json,
        ];
      }
    }
    return [
      'bytes' => 0,
      'json' => [],
    ];
  }

  public function find($select)
  {
    $this->node = null;

    if (isset($select['app_name'])) {
      $nids = \Drupal::entityQuery('node')
      ->condition('type', 'drupal_site')
      ->condition('field_application_name', $select['app_name'])
      ->execute();
    } else if (isset($select['host_url'])) {
      $nids = \Drupal::entityQuery('node')
      ->condition('type', 'drupal_site')
      ->condition('field_url', $select['host_url'])
      ->execute();
    }

    if (!empty($nids)) {
      $nid = array_shift($nids);
      $this->node = \Drupal\node\Entity\Node::load($nid);
    }

    return $this->node;
  }

  public function get_app_name()
  {
    if ($this->node) {
      return $this->node->get('field_application_name')->value;
    }
    return null;
  }

  public function get_host_url()
  {
    if ($this->node) {
      return preg_replace('/^http:/', 'https:', $this->node->get('field_url')->value);
    }
    return null;
  }

  /**
   * Update the drupal_site node with the log for the last backup event.
   * @param unknown $log
   * @return boolean
   */
  public function update_backup_log($log='')
  {
    if (!$this->node) {
      return false;
    }

    $this->node->set('field_last_backup_log', $log);

    $this->node->save();
    return true;
  }

  /**
   * Update the drupal_site node with the current time for the last backup/restore event.
   * @param unknown $event - 'backup' or 'restore'
   * @return boolean
   */
  public function update_event_time($event, $log='')
  {
    if (!$this->node) {
      return false;
    }

    $t = time() - 14400; // Correct GMT to EDT (4 hours)
    $datestr = date('Y-m-d', $t) . 'T' . date('H:i:s', $t);

    if ($event == 'backup') {
      $this->node->set('field_last_backup', $datestr);
      $this->node->set('field_last_backup_log', $log);
    } else if ($event == 'restore') {
      $this->node->set('field_last_restore', $datestr);
    }

    $this->node->save();
    return true;
  }

  /**
   * Get a configuration item specific to this module.
   * @param string $item
   * @return unknown
   */
  private function get_config($item)
  {
    static $conf = null;

    if (!$conf) {
      $conf = \Drupal::config('drmanage.settings');
    }

    return $conf->get($item);
  }

  /**
   * Send a request to the remote server, while keeping the connection alive between this server and the client (browser).
   * @param string $url
   * @param string $file name of file (for restore)
   * @return unknown
   */
  private function run_agent($url, $file=null)
  {
    // Create the request, which always includes S3 credentials
    $request = [
      'url' => $url,
      'postdata' => [
        'aws_access_key_id' => $this->get_config('s3_access_key'),
        'aws_secret_access_key' => $this->get_config('s3_secret_key'),
        'aws_s3_bucket' => $this->get_config('s3_host_bucket'),
        'aws_s3_region' => $this->get_config('s3_bucket_location'),
      ],
    ];

    if ($file) {
      $request['postdata']['filename'] = $file;
    }

    // Set up the file descriptors
    $descriptorspec = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w']
    ];

    $module_path = drupal_get_path('module', 'drmanage');

    // Run the agent through a pipe, which allows us to monitor the state
    $process = proc_open("/usr/bin/php $module_path/src/agent.php", $descriptorspec, $pipes);

    // Send the request into the agent
    fwrite($pipes[0], json_encode($request));
    fclose($pipes[0]);

    // Wait for the process to complete, while keeping the connection active
    $secs = 0;
    do {
      $secs++;
      sleep(1);
      $status = @proc_get_status($process);
      if (empty($status['running']) && !empty($status['stopped'])) {
        @proc_terminate($process);
        $status['running'] = false;
      }
      if (($secs % 5) == 0) { // Keep the connection alive by sending a newline every 5 secs
        echo PHP_EOL;
        flush();
        ob_flush();
      }
    } while (!empty($status['running']));

    // Get the result from the agent
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
      'bytes' => strlen($result),
      'data' => $result
    ];
  }
}
