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

    $app_name = $node->get('field_application_name')->value;
      
    $postdata = [
      'access_key' => $conf->get('s3_access_key'),
      'secret_key' => $conf->get('s3_secret_key'),
      'bucket_location' => $conf->get('s3_bucket_location'),  // e.g. ca-central-1
      'host_base' => $conf->get('s3_host_base'),              // e.g. s3.amazonaws.com
      'host_bucket' => $conf->get('s3_host_bucket'),          // DNS-style bucket name
      'app_name' => $app_name,
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
    $result = file_get_contents("$host_url/backup.php", false, $context);

    if ($result === FALSE) {
      $json['messages'][] = "Backup failed... exiting.";
      return new JsonResponse($json);
    }

    // edit date of last backup
    $t = time() - 14400;
    $url = $node->set('field_last_backup', date('Y-m-d', $t) . 'T' . date('H:i:s', $t));
    $node->save();

    $json = json_decode($result);
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
      'access_key' => $conf->get('s3_access_key'),
      'secret_key' => $conf->get('s3_secret_key'),
      'bucket_location' => $conf->get('s3_bucket_location'),  // e.g. ca-central-1
      'host_base' => $conf->get('s3_host_base'),              // e.g. s3.amazonaws.com
      'host_bucket' => $conf->get('s3_host_bucket'),          // DNS-style bucket name
      'backup_file' => $backup_file,
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
    $result = file_get_contents("$host_url/restore.php", false, $context);

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

}
