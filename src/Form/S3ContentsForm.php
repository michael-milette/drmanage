<?php

namespace Drupal\drmanage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class S3ContentsForm extends FormBase {

    public function getFormId() {
        return 'drmanage_s3contents';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

        $actions = array('Delete content');

        $header = [
            'file' => t('File'),
            'size' => t('Size'),
          ];

        $form['action'] = [
            '#type' => 'select',
            '#title' => 'Action',
            '#description' => '',
            '#options' => $actions,
            '#default_value' => 'Delete content',
        ];

        $form['s3contents'] = array(
            '#type' => 'tableselect',
            '#header' => $header,
            '#title' => '',
            '#options' => $this->getS3Contents(),
            '#empty' => t('No files in S3 bucket.'),
        );

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => 'Apply to selected items',
        ];

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
    }
  
    public function submitForm(array &$form, FormStateInterface $form_state){

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

        // Get selected values
        $results = [];
        $results = array_filter($form_state->getValue('s3contents'));
        $action = $form_state->getValue('action');
        
        switch ($action){

            case '0':
                // Delete content
                foreach ($results as $result) {

                    if ($fp = fopen('/tmp/tableselectEDD', 'a')) {
                        fwrite($fp, $result);
                        fclose($fp);
                    }

                    try {
                        $deleteItem = $s3->deleteObject([
                            'Bucket' => $s3_host_bucket,
                            'Key' => $result,
                        ]);
                    } catch (S3Exception $e) {
                        if ($fp = fopen('/tmp/s3deleteObject', 'a')) {
                            fwrite($fp, $deleteItem);
                            fclose($fp);
                        }
        
                    }
                }
                drupal_set_message(t('Files deleted successfully!'));
                break;
        }  
    }

    private function getS3Contents() {
        $contents = [];

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
          return $contents;
        }

        for ($n = 0; $n < sizeof($result['Contents']); $n++) {
          $contents[$result['Contents'][$n]['Key']] = [
              'file' => $result['Contents'][$n]['Key'],
              'size' => sprintf('%0.2f MB', $result['Contents'][$n]['Size'] / 1000000), // bytes to mb
          ];

        }
        return $contents;
    }
}