<?php

namespace Drupal\drmanage;

class DrupalSite {
  private $node = null;

  public function __construct()
  {
  }

  public function backup()
  {
    if ($this->node) {
      if ($result = $this->run_agent($this->get_host_url() . "/manage.php?operation=backup&verbose=true")) {
        return json_decode($result);
      }
    }
    return false;
  }

  public function restore()
  {
    if ($this->node) {
      if ($result = $this->run_agent($this->get_host_url() . "/manage.php?operation=restore&verbose=true")) {
        return json_decode($result);
      }
    }
    return false;
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
      return $node->get('field_application_name')->value;
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
   * Update the drupal_site node with the current time for the last backup/restore event.
   * @param unknown $event - 'backup' or 'restore'
   * @return boolean
   */
  public function update_event_time($event)
  {
    if (!$this->node) {
      return false;
    }

    $t = time() - 14400; // Correct GMT to EDT (4 hours)
    $datestr = date('Y-m-d', $t) . 'T' . date('H:i:s', $t);

    if ($event == 'backup') {
      $this->node->set('field_last_backup', $datestr);
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

    return $result;
  }
}
