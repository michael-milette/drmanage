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

  /**
   * Constructs HTML to update the radios element of the RestoreForm
   * given the URL of the selected host.
   * TODO - JSON response codes
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function update_restore_options() {
    
    $url = $_POST['host_url'];

    $nids = \Drupal::entityQuery('node')
        ->condition('type', 'drupal_site')
        ->condition('status', NODE_PUBLISHED)
        ->condition('field_url', $url, '=')
        ->execute();

    foreach ($nids as $nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      $app_name = $node->get('field_application_name')->value;
    }

    // Get AWS credentials from config
    $conf = \Drupal::config('drmanage.settings');

    $s3_access_key = $conf->get('s3_access_key');
    $s3_secret_key = $conf->get('s3_secret_key');
    $s3_bucket_location = $conf->get('s3_bucket_location');
    $s3_host_bucket = $conf->get('s3_host_bucket');

    putenv("AWS_ACCESS_KEY_ID=$s3_access_key");
    putenv("AWS_SECRET_ACCESS_KEY=$s3_secret_key");

    $s3 = new S3Client([
      'version' => 'latest',
      'region'  => $s3_bucket_location,
    ]);

    // Get bucket contents in app_name directory
    try {
      $result = $s3->listObjectsV2([
        'Bucket' => $s3_host_bucket,
        'Prefix' => $app_name,
      ]);
    } catch(S3Exception $e) {
      $json['html'][] = '<p>listObjectsV2 error...</p>';
      return new JsonResponse($json);
    }

    // Make an options list from the last 5 items
    $cnt = count($result['Contents']);
    $start = $cnt > 5 ? $cnt - 5 : 0;
    
    for ($n = $start; $n < $cnt; $n++) {

      // Format the selector options in drupalized html
      $patterns = array('/', '.tar.gz');
      $replacements = array('', 'targz');

      $filename = preg_replace($patterns, $replacements, $results['Contents'][$n]['Key']);

      $label = sprintf('%s (%0.1f MB)',
      $result['Contents'][$n]['Key'],
      $result['Contents'][$n]['Size'] / 1000000);
      
      $html =  '<div class="js-form-item form-item js-form-type-radio form-type--radio form-type--boolean js-form-item-restore form-item--restore">';
      $html .= '<input data-drupal-selector="edit-restore-' . $filename . '" ';
      $html .= 'type="radio" id="edit-restore-' . $filename . '" name="restore" ';
      $html .= 'value="' . $results['Contents'][$n]['Key'] . '"';
      $html .= 'class="form-radio form-boolean form-boolean--type-radio" tabindex="-1">';
      $html .= '<label for="edit-restore-' . $filename . '" ';
      $html .= 'class="form-item_label option">' . $label . '</label></div>';

      $json['html'][] = $html;
    }
    return new JsonResponse($json);
  }
}
