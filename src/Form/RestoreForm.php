<?php

namespace Drupal\drmanage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\Validator\Constraints\Length;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class RestoreForm extends FormBase {
    
    public function getFormId() {
        return 'drmanage_restoreform';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['host_url'] = [
            '#type' => 'textfield',
            '#title' => 'Host',
            '#description' => 'Host base URL',
            '#default_value' => 'https://manage-ciodrcoe-dev.apps.dev.openshift.ised-isde.canada.ca',
        ];

        $form['restore'] = array(
            '#type' => 'radios',
            '#title' => 'Select backup to restore',
            '#default_value' => '',
            '#options' => $this->getRestoreOptions(),
        );

        $form['response'] = [
            '#type' => 'textarea',
            '#title' => 'Response',
            '#rows' => 15,
            '#description' => '',
            '#default_value' => '',
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
   * Create an options list based on the most recent backups in the S3 bucket.
   * @return array|NULL[]
   */
  private function getRestoreOptions() {
    $options = [];

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

    // Get bucket contents
    try {
      $result = $s3->listObjectsV2([
        'Bucket' => $s3_host_bucket,
      ]);
    } catch(S3Exception $e) {
      return $options;
    }

    // Make an options list from the last 5 items
    $cnt = count($result['Contents']);
    $start = $cnt > 5 ? $cnt - 5 : 0;
    for ($n = $start; $n < $cnt; $n++) {
      $options[$result['Contents'][$n]['Key']] = sprintf('%s (%0.1f MB)',
        $result['Contents'][$n]['Key'],
        $result['Contents'][$n]['Size'] / 1000000
      );
    }

    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
  }

}