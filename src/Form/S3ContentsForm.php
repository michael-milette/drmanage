<?php

namespace Drupal\drmanage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class S3ContentsForm extends FormBase {

    public function getFormId() {
        return 'drmanage_s3contents';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

        $actions = array('Delete content', 'Download content');

        $header = [
            'file' => t('File'),
            'size' => t('Size'),
            'site_name' => t('Site Name'),
            'site_type' => t('Site Type'),
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

        // Check that at least one file is selected
        if (count($results) < 1) {
            drupal_set_message('No content selected.', $type = 'error');
            return;
        }

        $action = $form_state->getValue('action');
        switch ($action){

            case '0':
                // Delete content
                foreach ($results as $result) {
                    try {
                        $deleteItem = $s3->deleteObject([
                            'Bucket' => $s3_host_bucket,
                            'Key'    => $result,
                        ]);
                    } catch (S3Exception $e) {
                        if ($fp = fopen('/tmp/s3deleteObject', 'a')) {
                            fwrite($fp, $e);
                            fclose($fp);
                        }
                    }
                }
                drupal_set_message(t('Files deleted successfully!'));
                break;
            case '1':
                // Ensure only one file is selected
                // TODO - download multiple files
                if (count($results) > 1) {
                    drupal_set_message('Select only 1 file to download at a time.', $type = 'error');
                    break;
                }
                // Download file
                foreach ($results as $result) {
                    $filename = preg_replace('~^(.*?)\/~', '', $result);
                    $path = $_ENV['HOME'] . '/' . $filename;
                    try {
                        $downloadFile = $s3->getObject([
                            'Bucket' => $s3_host_bucket,
                            'Key'    => $result,
                            'SaveAs' => $path
                        ]);
                    } catch (S3Exception $e) {
                        if ($fp = fopen('/tmp/s3downloadFile', 'a')) {
                            fwrite($fp, $e);
                            fclose($fp);
                        }
                    }
                    // Open the file in binary mode
                    if ($fp = fopen($path, 'rb')) {
                        // Set headers
                        header("Content-Type: application/octet-stream");
                        header("Content-Length: " . filesize($path));
                        header("Content-Disposition: attachment; filename=\"$filename\"");
                        header("Connection: close");
                        // Download file to browser
                        fpassthru($fp);
                    }

                    // Delete backup file from app-root
                    exec("rm $path");
                }
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
        $s3Objects = [];
        $params = [
          'Bucket' => $s3_host_bucket
        ];

        do {
          try {
            $result = $s3->listObjectsV2($params);
          } catch(S3Exception $e) {
            return $contents;
          }
          $s3Objects = array_merge($s3Objects, $result['Contents']);
          $params['ContinuationToken'] = $result['NextContinuationToken'];
        } while ($result['IsTruncated']); // Will be true until there are no more objects to retrieve.

        foreach ($s3Objects as $s3Obj) {
         $n++;
          // Extract application name from backup file name
          $app_name = preg_match('/([^0-9]*)/', basename($s3Obj['Key']), $matches);
          $app_name = substr($matches[0], 0, -1);

          $info = $this->getSiteInfo($app_name);

          // Set results for each column
          $contents[$s3Obj['Key']] = [
            'file' => $s3Obj['Key'],
            'size' => sprintf('%0.2f MB', $s3Obj['Size'] / 1000000), // bytes to MB
            'site_name' => $info['name'],
            'site_type' => $info['type'],
          ];
        }

        return $contents;
    }

    private function getSiteInfo($app_name) {
        // Load Drupal node containing the selected host URL
        $nids = \Drupal::entityQuery('node')
        ->condition('type', 'drupal_site')
        ->condition('status', NODE_PUBLISHED)
        ->condition('field_application_name', $app_name, '=')
        ->execute();

        if(!empty($nids)){
            $nid = array_shift($nids);
            $node = \Drupal\node\Entity\Node::load($nid);

            $info = array (
                'name' => $node->getTitle(),
                'type' => $node->get('field_site_type')->value
            );
            return $info;
        }
        return null;
    }
}
