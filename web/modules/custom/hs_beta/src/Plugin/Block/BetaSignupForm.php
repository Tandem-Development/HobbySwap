<?php

namespace Drupal\hs_beta\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Provides 'BetaSignup' block
 *
 * @Block(
 *   id = "hs_beta_signup",
 *   admin_label = @Translation("HobbySwap Beta Signup Form"),
 *   category = @Translation("HobbySwap")
 * )
 */

class BetaSignupForm extends BlockBase{

  public function build() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\hs_beta\Form\BetaSignupForm');

    return[
      'form' => $form,
      '#attached' => [
        'library' => ['hs_beta/signup-form'],
      ],
    ];

  }

}
