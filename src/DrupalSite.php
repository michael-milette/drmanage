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

  public function start_backup_job()
  {
    if (!$this->node) {
      return ['status' => 'error'];
    }

    $postdata = [
      'aws_access_key_id' => $this->get_config('s3_access_key'),
      'aws_secret_access_key' => $this->get_config('s3_secret_key'),
      'aws_s3_bucket' => $this->get_config('s3_host_bucket'),
      'aws_s3_region' => $this->get_config('s3_bucket_location'),
    ];

    $options = [
      'http' => [ // use 'http' even if you send the request to https
        'header'  => "Content-type: application/x-www-form-urlencoded",
        'method'  => 'POST',
        'content' => http_build_query($postdata),
        'timeout' => 1000,
      ]
    ];

    $url = $this->get_host_url() . "/rmanage.php?operation=backup&job=true&verbose=true";
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $json = json_decode($result);

    if ($json->status == 'ok') {
      $this->node->set('field_backup_job_id', $json->job);
      $this->node->save();
    }

    return $json;
  }

  public function start_restore_job($s3file)
  {
    if (!$this->node) {
      return ['status' => 'error'];
    }

    $postdata = [
      'aws_access_key_id' => $this->get_config('s3_access_key'),
      'aws_secret_access_key' => $this->get_config('s3_secret_key'),
      'aws_s3_bucket' => $this->get_config('s3_host_bucket'),
      'aws_s3_region' => $this->get_config('s3_bucket_location'),
    ];

    $options = [
      'http' => [ // use 'http' even if you send the request to https
        'header'  => "Content-type: application/x-www-form-urlencoded",
        'method'  => 'POST',
        'content' => http_build_query($postdata),
        'timeout' => 1000,
      ]
    ];

    $url = $this->get_host_url() . "/rmanage.php?operation=restore&s3file=$s3file&job=true&verbose=true";
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $json = json_decode($result);

    if ($json->status == 'ok') {
      $this->node->set('field_restore_job_id', $json->job);
      $this->node->save();
    }

    return $json;
  }

  public function query_job($job)
  {
    if (!$this->node) {
      return ['status' => 'error'];
    }

    $url = $this->get_host_url() . "/rmanage.php?operation=query&job=$job";
    $result = file_get_contents($url);
    $json = json_decode($result);
    return $json;
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

  public function get_backup_job_id()
  {
    if ($this->node) {
      return $this->node->get('field_backup_job_id')->value;
    }
    return null;
  }

  public function xget_backup_results($job)
  {
    $json = null;

    $json = $this->query_job($job);

    if (!empty($json->status)) {
      $this->node->set('field_backup_job_id', null);
      if (!empty($json->messages)) {
        $this->node->set('field_last_backup_log', join("\n", $json->messages));
      }
      if ($json->status == 'ok') {
        $t = isset($json->end_time) ? strtotime($json->end_time) : time() - 14400; // Correct GMT to EDT (4 hours)
        $datestr = date('Y-m-d', $t) . 'T' . date('H:i:s', $t);
        $this->node->set('field_last_backup', $datestr);
      }
      $this->node->save();
    }

    return $json;
  }

  public function get_host_url()
  {
    if ($this->node) {
      return preg_replace('/^http:/', 'https:', $this->node->get('field_url')->value);
    }
    return null;
  }

  public function get_restore_job_id()
  {
    if ($this->node) {
      return $this->node->get('field_restore_job_id')->value;
    }
    return null;
  }

  public function get_results($job)
  {
    $json = null;

    $json = $this->query_job($job);

    if (!empty($json->status)) {
      $datestr = null;
      if ($json->status == 'ok') {
        $t = isset($json->end_time) ? strtotime($json->end_time) : time() - 14400; // Correct GMT to EDT (4 hours)
        $datestr = date('Y-m-d', $t) . 'T' . date('H:i:s', $t);

        if ($this->node->get('field_backup_job_id')->value == $job) {
          $this->node->set('field_backup_job_id', null);
          $this->node->set('field_last_backup', $datestr);
          $this->node->set('field_last_backup_log', empty($json->messages) ? '' : join("\n", $json->messages));
        } elseif ($this->node->get('field_restore_job_id')->value == $job) {
          $this->node->set('field_restore_job_id', null);
          $this->node->set('field_last_restore', $datestr);
          $this->node->set('field_last_restore_log', empty($json->messages) ? '' : join("\n", $json->messages));
        }

        $this->node->save();
      }
    }

    return $json;
  }

  public function xget_restore_results($job)
  {
    $json = null;

    $json = $this->query_job($job);

    if (!empty($json->status)) {
      $this->node->set('field_restore_job_id', null);
      if (!empty($json->messages)) {
        $this->node->set('field_last_restore_log', join("\n", $json->messages));
      }
      if ($json->status == 'ok') {
        $t = isset($json->end_time) ? strtotime($json->end_time) : time() - 14400; // Correct GMT to EDT (4 hours)
        $datestr = date('Y-m-d', $t) . 'T' . date('H:i:s', $t);
        $this->node->set('field_last_restore', $datestr);
      }
      $this->node->save();
    }

    return $json;
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
}
