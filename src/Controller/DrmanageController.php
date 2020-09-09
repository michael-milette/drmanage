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
   * Handle a backup request.
   * This function will contact the remote host and initiate a backup.
   * TODO - JSON response codes
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function request_backup() {
    $host_url = $_POST['host_url'];

    // Need to put out a header now because this will output newlines to keep the connection open
    header('Content-type: application/json');
    header('Connection: keep-alive');

    $site = new DrupalSite();

    if (!$site->find(['host_url' => $host_url])) {
      return new JsonResponse(['status' => 'error', 'messages' => ['Cannot open site record.']]);
    }

    // Send the backup request
    $result = $site->backup();

    if ($result['bytes'] == 0) {
      return new JsonResponse(['status' => 'error', 'messages' => ['Backup failed. Connection lost.']]);
    }
    if (empty($result['json'])) {
      return new JsonResponse(['status' => 'error', 'messages' => ['JSON decode error.']]);
    }

    return new JsonResponse($result['json']);
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

    $site = new DrupalSite();

    if (!$site->find(['host_url' => $host_url])) {
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
    $result = file_get_contents("$host_url/manage.php?operation=space");
    $json = json_decode($result);

    return [
      '#theme' => 'site-info',
      '#appName' => $appName,
      '#hostUrl' => $host_url,
      '#json' => $json,
    ];
  }
}
