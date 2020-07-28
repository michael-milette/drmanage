<?php

namespace Drupal\drmanage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\HttpFoundation\JsonResponse;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class RestoreForm extends FormBase {
    
    public function getFormId() {
        return 'drmanage_restoreform';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

        $hosts = [];

        $nids = \Drupal::entityQuery('node')
        ->condition('type', 'drupal_site')
        ->condition('status', NODE_PUBLISHED)
        ->execute();

      
        foreach ($nids as $nid) {
          $node = \Drupal\node\Entity\Node::load($nid);
          $url = $node->get('field_url')->value;
          $title = $node->getTitle();
          $hosts[$url] = $title;
          $app_name[$url] = $node->get('field_application_name')->value;
        }

        natcasesort($hosts);

        $urls = array_keys($hosts);
        $default_appname = $app_name[$urls[0]];

        $form['host_url'] = [
            '#type' => 'select',
            '#title' => 'Host',
            '#description' => 'Host URL to restore to',
            '#options' => $hosts,
            '#default_value' => 'Select a host.',
            '#attributes' => [
              'onchange' => 'updateRestoreOptions(this)'
            ],
        ];

        $form['restore'] = array(
            '#type' => 'radios',
            '#title' => 'Select backup to restore',
            '#default_value' => '',
            '#options' => $this->getRestoreOptions($default_appname, FALSE),
        );

        $form['response'] = [
            '#type' => 'textarea',
            '#title' => 'Response',
            '#rows' => 18,
            '#description' => '',
        ];

          $form['submit'] = [
            '#type' => 'submit',
            '#value' => 'Restore!',
            '#tableselect' => False,
            '#tabledrag' => False,
            '#attributes' => [
              'onclick' => 'return submitRestoreForm()'
            ],
        ];

        $form_state->disableRedirect(true);

        return $form;
    }

  /**
   * Update 'restore' element radio options given the selected host.
   * TODO - JSON response codes
   * @return 5 most recent backup file options
   */
  public function getRestoreOptions($app_name = '', $onChange = TRUE) {

    // Get AWS credentials from config
    $conf = \Drupal::config('drmanage.settings');

    $s3_access_key = $conf->get('s3_access_key');
    $s3_secret_key = $conf->get('s3_secret_key');
    $s3_bucket_location = $conf->get('s3_bucket_location');
    $s3_host_bucket = $conf->get('s3_host_bucket');

    putenv("AWS_ACCESS_KEY_ID=$s3_access_key");
    putenv("AWS_SECRET_ACCESS_KEY=$s3_secret_key");

    // Initialize S3 client
    $s3 = new S3Client([
      'version' => 'latest',
      'region'  => $s3_bucket_location,
    ]);
    
    if ($onChange) {
        $url = $_POST['host_url'];

        $nids = \Drupal::entityQuery('node')
            ->condition('type', 'drupal_site')
            ->condition('status', NODE_PUBLISHED)
            ->condition('field_url', $url, '=')
            ->execute();

        if(!empty($nids)){
            $nid = array_shift($nids);
            $node = \Drupal\node\Entity\Node::load($nid);
        } else {
            $json['html'][] = "<div><p>Unable to load node... exiting.</p></div>";
            return new JsonResponse($json);
        }
        $app_name = $node->get('field_application_name')->value;

        // Get bucket contents in app_name directory
        try {
            $result = $s3->listObjectsV2([
              'Bucket' => $s3_host_bucket,
              'Prefix' => $app_name,
            ]);
        } catch(S3Exception $e) {
            $json['html'][] = "<div><p>listObjectsV2 error... exiting.</p></div>";
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

        $label = sprintf('%s (%0.2f MB)',
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

    } else {
        $options = [];

        // Get bucket contents in app_name directory
        try {
            $result = $s3->listObjectsV2([
            'Bucket' => $s3_host_bucket,
            'Prefix' => $app_name,
            ]);
        } catch(S3Exception $e) {
            return $options;
        }

        // Make an options list from the last 5 items
        $cnt = count($result['Contents']);
        $start = $cnt > 5 ? $cnt - 5 : 0;
        for ($n = $start; $n < $cnt; $n++) {
        $options[$result['Contents'][$n]['Key']] = sprintf('%s (%0.2f MB)',
            $result['Contents'][$n]['Key'],
            $result['Contents'][$n]['Size'] / 1000000
        );
        }  
        return $options;
    } 
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
  }
}
