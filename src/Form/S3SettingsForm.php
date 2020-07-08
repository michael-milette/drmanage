<?php

namespace Drupal\drmanage\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class S3SettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 's3_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'drmanage.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('drmanage.settings');

    $form['s3_access_key'] = [
      '#type' => 'textfield',
      '#title' => 'Access Key',
      '#description' => 'S3 Access Key',
      '#default_value' => $config->get('s3_access_key'),
    ];

    $form['s3_secret_key'] = [
      '#type' => 'textfield',
      '#title' => 'Secret Key',
      '#description' => 'S3 Secret Key',
      '#default_value' => $config->get('s3_secret_key'),
    ];

    $form['s3_bucket_location'] = [
      '#type' => 'textfield',
      '#title' => 'Bucket location',
      '#description' => 'S3 Region',
      '#default_value' => $config->get('s3_bucket_location') ? $config->get('s3_bucket_location') : 'ca-central-1',
    ];

    $form['s3_host_base'] = [
      '#type' => 'textfield',
      '#title' => 'Host base',
      '#description' => 'S3 host base',
      '#default_value' => $config->get('s3_host_base') ? $config->get('s3_host_base') : 's3.amazonaws.com',
    ];

    $form['s3_host_bucket'] = [
      '#type' => 'textfield',
      '#title' => 'Host bucket',
      '#description' => 'DNS-style bucket name',
      '#default_value' => $config->get('s3_host_bucket'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('drmanage.settings');

    $settings = [
      's3_access_key',
      's3_secret_key',
      's3_bucket_location',
      's3_host_base',
      's3_host_bucket',
    ];

    foreach ($settings as $setting) {
      $config->set($setting, $form_state->getValue($setting));
    }
    $config->save();
  }
}
