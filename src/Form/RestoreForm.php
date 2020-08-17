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
        $backup_type = array(
            'daily' => 'Daily', 
            'weekly'=> 'Weekly',
            'monthly' => 'Monthly'
        );

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
            '#attributes' => [
              'onchange' => 'updateRestoreOptions()'
            ],
        ];

        $form['backup_type'] = [
            '#type' => 'select',
            '#title' => 'Type of backup file',
            '#options' => $backup_type,
            '#attributes' => [
                'onchange' => 'updateRestoreOptions()'
            ],
        ];

        $form['restore'] = array(
            '#type' => 'radios',
            '#title' => 'Select backup file to restore',
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
   * @return 10 most recent backup file options
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
        $backup_type = $_POST['backup_type'];

        // Load Drupal node containing the selected host URL
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

        // Disregard environment information in app_name
        $app_name = $this->generalizeAppName($node->get('field_application_name')->value);

        // Get bucket contents in app_name directory
        try {
            $result = $s3->listObjectsV2([
              'Bucket' => $s3_host_bucket,
              'Prefix' => $backup_type . '/' . $app_name,
            ]);
        } catch(S3Exception $e) {
            $json['html'][] = "<div><p>listObjectsV2 error... exiting.</p></div>";
            return new JsonResponse($json);
        }

        if (isset($result['Contents'])) {
            // Make an options list from the last 10 items
            $cnt = count($result['Contents']);
            $start = $cnt > 10 ? $cnt - 10 : 0;
            for ($n = $start; $n < $cnt; $n++) {

                // Format the selector options in drupalized html
                $patterns = array('~/~', '~.tar.gz~');
                $replacements = array('', 'targz');

                $filename = preg_replace($patterns, $replacements, $result['Contents'][$n]['Key']);

                $label = sprintf('%s (%0.2f MB)',
                $result['Contents'][$n]['Key'],
                $result['Contents'][$n]['Size'] / 1000000);
                
                $html =  '<div class="js-form-item form-item js-form-type-radio form-type--radio form-type--boolean js-form-item-restore form-item--restore">';
                $html .= '<input data-drupal-selector="edit-restore-' . $filename . '" ';
                $html .= 'type="radio" id="edit-restore-' . $filename . '" name="restore" ';
                $html .= 'value="' . $result['Contents'][$n]['Key'] . '"';
                $html .= 'class="form-radio form-boolean form-boolean--type-radio" tabindex="-1">';
                $html .= '<label for="edit-restore-' . $filename . '" ';
                $html .= 'class="form-item_label option">' . $label . '</label></div>';
                $json['html'][] = $html;
            }
        } else {
            $json['html'][] = '<div><p>No backup files found.</p></div>';
        }
        return new JsonResponse($json);

    } else {
        $options = [];

        // Disregard environment information in app_name
        $app_name = $this->generalizeAppName($app_name);

        // Get bucket contents in app_name directory
        try {
            $result = $s3->listObjectsV2([
            'Bucket' => $s3_host_bucket,
            'Prefix' => 'daily/' . $app_name,
            ]);
        } catch(S3Exception $e) {
            return $options;
        } 

        if (isset($result['Contents'])) {
            // Make an options list from the last 10 items
            $cnt = count($result['Contents']);
            $start = $cnt > 10 ? $cnt - 10 : 0;
            for ($n = $start; $n < $cnt; $n++) {
                $options[$result['Contents'][$n]['Key']] = sprintf('%s (%0.2f MB)',
                    $result['Contents'][$n]['Key'],
                    $result['Contents'][$n]['Size'] / 1000000
                );
            }  
        } else {
            $options[] = 'No backup files found.';
        }
        return $options;
    } 
  }

  /** Helper function to remove environment information (dev, qa, test, etc.)
   *  from application name. The current implementation lists the restore options
   *  for all application environments.
   * 
   *  @return string
   */
  private function generalizeAppName($app_name) {
    if ($app_name == 'localhost') {
        return '';
    } else {
        preg_match('/^[^-]*/', $app_name, $matches);
        return $matches[0];
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
  }
}
