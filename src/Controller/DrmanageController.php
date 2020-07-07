<?php

namespace Drupal\drmanage\Controller;

//use Drupal\Core\Controller\ControllerBase;
//use Drupal\Core\Render\HtmlResponse;
//use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\RedirectResponse;

class DrmanageController {
  public function dashboard()
  {
    return [
      '#theme' => 'dashboard',
      '#data' => [],
    ];
  }

  public function backup() {
    $url = $data['website_url'];

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
    return [
      '#theme' => 'listcontents',
      '#data' => [],
    ];
  }
}
