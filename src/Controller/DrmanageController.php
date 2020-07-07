<?php

namespace Drupal\drmanage\Controller;

//use Drupal\Core\Controller\ControllerBase;
//use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\RedirectResponse;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class DrmanageController {
  public function dashboard()
  {
    return [
      '#theme' => 'dashboard',
      '#data' => [],
    ];
  }

  public function sendreq() {
    $host_url = $_POST['host_url'];
    $somevalue = $_POST['somevalue'];

    $postdata = [
      'somevalue' => $somevalue,
    ];

    // use key 'http' even if you send the request to https://...
    $options = [
      'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($postdata)
      ]
    ];

    $target_url = "https://manage-ciodrcoe-dev.apps.dev.openshift.ised-isde.canada.ca/test.php";

    $context  = stream_context_create($options);
    $result = file_get_contents($target_url, false, $context);
    $json = json_decode($result);

    return new JsonResponse($json);
  }

  public function backup() {

    $url = 'https://manage-ciodrcoe-dev.apps.dev.openshift.ised-isde.canada.ca/backup.php';

    $postdata = [
      'access_key' => $data['aws_access_key'],
      'secret_key' => $data['aws_secret_key'],
      'bucket_location' => $data['default_region'],
      'host_base' => 's3.amazonaws.com',
      'host_bucket' => $data['dns_style_bucket_hostname']
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
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
      drupal_set_message('Failed for some reason', 'error');
    }
    else {
      drupal_set_message('Success! Maybe. '. print_r($result, true));
    }
  }

  public function listContents() {

    // Get AWS credentials from config
    $conf = \Drupal::config('drmanage.settings');

    $s3_access_key = $conf->get('s3_access_key');
    $s3_secret_key = $conf->get('s3_secret_key');
    $s3_session_token = $conf->get('s3_session_token');
    $s3_bucket_location = $conf->get('s3_bucket_location');
    $s3_host_bucket = $conf->get('s3_host_bucket');

    putenv("AWS_ACCESS_KEY_ID=$s3_access_key");
    putenv("AWS_SECRET_ACCESS_KEY=$s3_secret_key");
    putenv("AWS_SESSION_TOKEN=$s3_session_token");

    $s3 = new S3Client([
      'version' => 'latest',
      'region'  => $s3_bucket_location,
    ]);

    try {
      // Request list of objects in s3 bucket
      $result = $s3->listObjectsV2([
        'Bucket' => $s3_host_bucket,
      //'MaxKeys' => 10, // default 1000
      ]);
    } catch(S3Exception $e) {
      // Print error message in /tmp/listcontents_error
      if ($fp = fopen('/tmp/listcontents_error', 'a')) {
        fwrite($fp, $e->getMessage());
        fclose($fp);
      }
    }

    // Format results in html table
    $content = '<h3>Bucket Name: ' . $result['Name'] . '</h3>' .  '<h3>Objects Found: ' . $result['KeyCount'] . '</h3>';
    $content .= '<table class="table">';
    $content .= '<tr><th>File</th><th>Size</th><th>LastModified</th>';
    for ($n = 0; $n <sizeof($result['Contents']); $n++) {
      $content .= '<tr>';
      $content .= '<td>' . $result['Contents'][$n]['Key'] . '</td>';
      $content .= '<td>' . $result['Contents'][$n]['Size'] . '</td>';
      $content .= '<td>' . $result['Contents'][$n]['LastModified'] . '</td>';
      $content .= '<tr>';
    }
    $content .= '</table>';

    // Print content in /tmp/listcontents
    if ($fp = fopen('/tmp/listcontents', 'a')) {
      fwrite($fp, $content);
      fclose($fp);
    }

    return [
      '#theme' => 'listcontents',
      '#content' => $content,
    ];
  }
}
