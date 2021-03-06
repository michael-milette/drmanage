<?php

namespace Drupal\drmanage\Controller;

//use Drupal\Core\Controller\ControllerBase;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Drupal\Core\Render\HtmlResponse;
use Drupal\drmanage\DrupalSite;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\RedirectResponse;

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
    $result = $site->get_results($job);

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
    $result = $site->start_restore_job($backup_file);
    return new JsonResponse($result);
  }

  public function enableMaint(NodeInterface $node)
  {
    $site = new DrupalSite($node);
    $status = $site->set_maintmode('on');

    if (is_string($status)) {
      $build['content'] = [
        '#type' => 'item',
        '#markup' => 'Maintenance mode is set to "' . $status . '" for ' . $node->getTitle(),
      ];
      return $build;
    }

    $build['content'] = [
      '#type' => 'item',
      '#markup' => 'error setting mode for ' . $site->getTitle(),
    ];
    return $build;
  }

  public function disableMaint(NodeInterface $node)
  {
    $site = new DrupalSite($node);
    $status = $site->set_maintmode('off');
    if (is_string($status)) {
      $build['content'] = [
        '#type' => 'item',
        '#markup' => 'Maintenance mode is set to "' . $status . '" for ' . $node->getTitle(),
      ];
      return $build;
    }

    $build['content'] = [
      '#type' => 'item',
      '#markup' => 'error setting mode',
    ];
    return $build;
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
