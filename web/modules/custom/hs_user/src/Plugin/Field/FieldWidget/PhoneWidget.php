<?php

namespace Drupal\hs_user\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A widget for phone number form elements
 * @FieldWidget(
 *   id = "hs_user_phone",
 *   label = @Translation("Phone"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class PhoneWidget extends WidgetBase{

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state){

    $value = isset($items[$delta]->value) ? $items[$delta]->value : '';
    $element += [
      '#type' => 'textfield',
      '#default_value' => $value,
      '#size' => 13,
      '#maxlength' => 12,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
      '#attached' => [
        'library' => ['hs_user/phone-widget-formatter']
      ],
      '#attributes' => [
        'class' => ['input--phone'],
        'placeholder' => '000-000-0000'
      ],
    ];

    return ['value' => $element];
  }

  public static function validate($element, FormStateInterface $form_state){
    $value = $element['#value'];
    if(strlen($value) !== 10){
      $form_state->setError($element, 'Must be a valid phone number');
    }
  }

}