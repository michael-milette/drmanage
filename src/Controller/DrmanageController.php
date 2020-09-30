<?php

namespace Drupal\drmanage\Controller;

//use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\RedirectResponse;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Drupal\drmanage\DrupalSite;

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
   * Initiate an asynchronous backup request.
   * This function will contact the remote host and initiate a backup.
   * TODO - JSON response codes
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function request_backup() {
    $app_name = $_POST['app_name'];

    // Need to put out a header now because this will output newlines to keep the connection open
    header('Content-type: application/json');

    $site = new DrupalSite();

    if (!$site->find(['app_name' => $app_name])) {
      return new JsonResponse(['status' => 'error', 'messages' => ['Cannot open site record.']]);
    }

    // Send the backup request
    $result = $site->start_backup_job();
    return new JsonResponse($result);
  }

  public function query_job($job)
  {
    $app_name = $_POST['app_name'];

    // Need to put out a header now because this will output newlines to keep the connection open
    header('Content-type: application/json');

    $site = new DrupalSite();

    if (!$site->find(['app_name' => $app_name])) {
      return new JsonResponse(['status' => 'error', 'messages' => ['Cannot open site record.']]);
    }

    // Get the results. If the backup is complete, it will also store the result.
    $result = $site->get_backup_results($job);

    return new JsonResponse($result);
  }

  /**
   * Handle a restore request.
   * This function will contact the remote host and initiate a restore.
   * TODO - JSON response codes
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function request_restore() {
    $app_name = $_POST['app_name'];
    $backup_file = $_POST['backup_file'];

    $site = new DrupalSite();

    if (!$site->find(['app_name' => $app_name])) {
      return new JsonResponse(['status' => 'error', 'messages' => ['Cannot open site record.']]);
    }

    // Send the restore request
    $result = $site->restore();

    if ($result['bytes'] == 0) {
      return new JsonResponse(['status' => 'error', 'messages' => ['Restore failed. Connection lost.']]);
    }
    if (empty($result['json'])) {
      return new JsonResponse(['status' => 'error', 'messages' => ['JSON decode error.']]);
    }

    return new JsonResponse($result['json']);
  }

  public function site_status(string $appName)
  {
    $site = new DrupalSite();

    if (!$site->find(['app_name' => $appName])) {
      return new HtmlResponse("Cannot load site node. Does that site exist?");
    }

    $host_url = $site->get_host_url();
    $result = file_get_contents("$host_url/rmanage.php?operation=space");
    $json = json_decode($result);

    return [
      '#theme' => 'site-info',
      '#appName' => $appName,
      '#hostUrl' => $host_url,
      '#json' => $json,
    ];
  }
}
