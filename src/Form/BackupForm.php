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
    }

    natcasesort($hosts);

    $form['host_url'] = [
      '#type' => 'select',
      '#title' => 'Host',
      '#description' => 'Host to back up',
      '#options' => $hosts,
      '#default_value' => '',
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => 'Response',
      '#rows' => 15,
      '#description' => '',
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
