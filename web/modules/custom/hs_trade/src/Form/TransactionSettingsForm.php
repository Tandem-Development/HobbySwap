<?php

/**
 * Boilerplate entity settings form
 * Nothing transaction specific
 */

namespace Drupal\hs_trade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @ingroup hs_trade
 */
class TransactionSettingsForm extends FormBase {

  /**
   * @return string
   */
  public function getFormId() {
    return 'hs_trade_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty implementation of the abstract submit class.
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['transaction_settings']['#markup'] = 'Settings form for hs_trade. Manage field settings here.';
    return $form;
  }

}
