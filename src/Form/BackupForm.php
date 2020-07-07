<?php

namespace Drupal\drmanage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\Validator\Constraints\Length;

class BackupForm extends FormBase {

  public function getFormId() {
    return 'drmanage_backupform';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['host_url'] = [
      '#type' => 'textfield',
      '#title' => 'Host',
      '#description' => 'Host base URL',
      '#default_value' => 'https://manage-ciodrcoe-dev.apps.dev.openshift.ised-isde.canada.ca',
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => 'Response',
      '#rows' => 15,
      '#description' => '',
      '#default_value' => '',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Backup!',
      '#tableselect' => False,
      '#tabledrag' => False,
      '#attributes' => [
        'onclick' => 'return submitBackupForm()'
      ],
    ];

    $form_state->disableRedirect(true);

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
