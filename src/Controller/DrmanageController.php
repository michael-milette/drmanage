<?php

namespace Drupal\drmanage\Controller;

//use Drupal\Core\Controller\ControllerBase;
//use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\RedirectResponse;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class DrmanageController {
  /**
   * Display the Drupal Management Dashboard
   * @return string[]|array[]
   */
  public function dashboard()
  {
    return [
      '#theme' => 'dashboard',
      '#data' => [],
    ];
  }

  /**
   * Handle a backup request.
   * This function will contact the remote host and initiate a backup.
   * TODO - JSON response codes
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function request_backup() {
    $host_url = $_POST['host_url'];

    // Need to put out a header now because this will output newlines to keep the connection open
    header('Content-type: application/json');

    // Send the backup request
    $result = $this->run_agent("$host_url/manage.php?operation=backup&verbose=true");

    if ($result === false) {
      return new JsonResponse(['status' => 'error', 'errmsg' => 'Backup failed.']);
    }

    $json = json_decode($result);

    if (!$this->update_event_time('backup', $host_url)) {
      $json['messages'][] = "Unable to update last-backup time.";
    }

    return new JsonResponse($json);
  }

  /**
   * Handle a restore request.
   * This function will contact the remote host and initiate a restore.
   * TODO - JSON response codes
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function request_restore() {
    $host_url = $_POST['host_url'];
    $backup_file = $_POST['backup_file'];

    // Get AWS credentials from config
    $conf = \Drupal::config('drmanage.settings');

    $nids = \Drupal::entityQuery('node')
    ->condition('type', 'drupal_site')
    ->condition('field_url', $host_url, '=')
    ->execute();

    if (!empty($nids)) {
      $nid = array_shift($nids);
      $node = \Drupal\node\Entity\Node::load($nid);
    } else {
      $json['messages'][] = "Unable to load node... exiting.";
      return new JsonResponse($json);
    }

    $postdata = [
      'aws_access_key_id' => $conf->get('s3_access_key'),
      'aws_secret_access_key' => $conf->get('s3_secret_key'),
      'aws_s3_bucket' => $conf->get('s3_host_bucket'),      // DNS-style bucket name
      'aws_s3_region' => $conf->get('s3_bucket_location'),  // e.g. ca-central-1
      'filename' => $backup_file,
    ];

    // use key 'http' even if you send the request to https://...
    $options = [
      'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($postdata)
      ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents("$host_url/manage.php?operation=restore", false, $context);

    if ($result === FALSE) {
      $json['messages'][] = "Restore failed... exiting.";
      return new JsonResponse($json);
    }

    // edit date of last restore
    $t = time() - 14400;
    $url = $node->set('field_last_restore', date('Y-m-d', $t) . 'T' . date('H:i:s', $t));
    $node->save();

    $json = json_decode($result);
    return new JsonResponse($json);
  }

  public function site_status(string $appName)
  {
    $nids = \Drupal::entityQuery('node')
    ->condition('type', 'drupal_site')
    ->condition('field_application_name', $appName)
    ->execute();

    $hostUrl = '';

    if (!empty($nids)) {
      $nid = array_shift($nids);
      $node = \Drupal\node\Entity\Node::load($nid);
      $host_url = $node->get('field_url')->value;
    }

    $json = [
      'status' => "success",
      'data' => [
        [
          'volume' => "/opt/app-root/src/data",
          'totalspace' => "1023303680",
          'freespace' => "1000169472",
          'usedspace' => "23134208",
          'usedpercentage' => "2.26%",
        ]
      ]
    ];

    $host_url = preg_replace('/^http:/', 'https:', $host_url);
    $result = file_get_contents("$host_url/manage.php?operation=space");
    $json = json_decode($result);

    return [
      '#theme' => 'site-info',
      '#appName' => $appName,
      '#hostUrl' => $host_url,
      '#json' => $json,
    ];
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
   * Get the absolute path to this moodule.
   * @return unknown
   */
  private function module_path()
  {
    return drupal_get_path('module', 'drmanage');
  }

  /**
   * Send a request to the remote server, while keeping the connection alive between this server and the client (browser).
   * @param string $url
   * @return unknown
   */
  private function run_agent($url)
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

    // Set up the file descriptors
    $descriptorspec = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w']
    ];

    // Run the agent through a pipe, which allows us to monitor the state
    $process = proc_open('/usr/bin/php ' . $this->module_path() . '/src/agent.php', $descriptorspec, $pipes);

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

  /**
   * Update the drupal_site node with the time of the last backup/restore event.
   * @param unknown $event
   * @param unknown $host_url
   * @return boolean
   */
  private function update_event_time($event, $host_url)
  {
    $nids = \Drupal::entityQuery('node')
    ->condition('type', 'drupal_site')
    ->condition('field_url', $host_url, '=')
    ->execute();

    if (!empty($nids)) {
      $nid = array_shift($nids);
      if ($node = \Drupal\node\Entity\Node::load($nid)) {
        $t = time() - 14400;
        $datestr = date('Y-m-d', $t) . 'T' . date('H:i:s', $t);
        if ($event == 'backup') {
          $url = $node->set('field_last_backup', $datestr);
          $node->save();
          return true;
        } else if ($event == 'restore') {
          $url = $node->set('field_last_restore', $datestr);
          $node->save();
          return true;
        }
      }
    }

    return false;
  }
}
