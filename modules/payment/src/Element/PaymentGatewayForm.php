<?php

namespace Drupal\commerce_payment\Element;

use Drupal\commerce\Element\CommerceElementTrait;
use Drupal\commerce_payment\Entity\EntityWithPaymentGatewayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElement;

@trigger_error('The ' . __NAMESPACE__ . '\PaymentGatewayForm is deprecated. Instead, use the payment_gateway_form inline form. See https://www.drupal.org/node/3015309.', E_USER_DEPRECATED);

/**
 * Provides a form element for embedding the payment gateway forms.
 *
 * @deprecated Use the payment_gateway_form inline form instead.
 *
 * Usage example:
 * @code
 * $form['payment_method'] = [
 *   '#type' => 'commerce_payment_gateway_form',
 *   '#operation' => 'add-payment-method',
 *   // A payment or payment method entity, depending on the operation.
 *   // On submit, the payment method will be created remotely, and the
 *   // entity updated, for access via $form_state->getValue('payment_method')
 *   '#default_value' => $payment_method,
 * ];
 * @endcode
 *
 * @RenderElement("commerce_payment_gateway_form")
 */
class PaymentGatewayForm extends RenderElement {

  use CommerceElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#operation' => '',
      // The entity operated on. Instance of EntityWithPaymentGatewayInterface.
      '#default_value' => NULL,
      // The url to which the user will be redirected if an exception is thrown
      // while building the form. If empty, the error will be shown inline.
      '#exception_url' => '',
      '#exception_message' => t('An error occurred while contacting the gateway. Please try again later.'),

      '#process' => [
        [$class, 'attachElementSubmit'],
        [$class, 'processForm'],
      ],
      '#element_validate' => [
        [$class, 'validateElementSubmit'],
        [$class, 'validateForm'],
      ],
      '#commerce_element_submit' => [
        [$class, 'submitForm'],
      ],
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Builds the payment gateway form.
   *
   * @param array $element
   *   The form element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the #operation or #default_value properties are empty, or
   *   when the #default_value property is not a valid entity.
   *
   * @return array
   *   The processed form element.
   */
  public static function processForm(array $element, FormStateInterface $form_state, array &$complete_form) {
    if (empty($element['#operation'])) {
      throw new \InvalidArgumentException('The commerce_payment_gateway_form element requires the #operation property.');
    }
    if (empty($element['#default_value'])) {
      throw new \InvalidArgumentException('The commerce_payment_gateway_form element requires the #default_value property.');
    }
    elseif (isset($element['#default_value']) && !($element['#default_value'] instanceof EntityWithPaymentGatewayInterface)) {
      throw new \InvalidArgumentException('The commerce_payment_gateway_form #default_value property must be a payment or a payment method entity.');
    }

    /** @var \Drupal\commerce\InlineFormManager $inline_form_manager */
    $inline_form_manager = \Drupal::service('plugin.manager.commerce_inline_form');
    $inline_form = $inline_form_manager->createInstance('payment_gateway_form', [
      'operation' => $element['#operation'],
      'exception_url' => $element['#exception_url'],
      'exception_message' => $element['#exception_message'],
    ], $element['#default_value']);

    $element['#inline_form'] = $inline_form;
    $element = $inline_form->buildInlineForm($element, $form_state);

    return $element;
  }

  /**
   * Validates the payment gateway form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateForm(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $element['#inline_form'];
    $inline_form->validateInlineForm($element, $form_state);
  }

  /**
   * Submits the payment gateway form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitForm(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $element['#inline_form'];
    $inline_form->submitInlineForm($element, $form_state);
    $form_state->setValueForElement($element, $inline_form->getEntity());
  }

}
