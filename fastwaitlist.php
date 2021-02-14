<?php

require_once 'fastwaitlist.civix.php';
use CRM_Fastwaitlist_ExtensionUtil as E;

/**
 * This code runs AFTER the postProcess hook.  postProcess handles the participant status change etc., then reloads the page.
 * This code is to show an appropriate status message when the page reloads.
 *
 */
function fastwaitlist_civicrm_buildForm($formName, &$form) {
  // Is this the waitlist confirmation screen? If not, bye.
  if ($formName != "CRM_Event_Form_Registration_ParticipantConfirm") {
    return;
  }
  // Is this a paid event? If not, bye.
  $event = \Civi\Api4\Event::get()
    ->addWhere('id', '=', $form->_eventId)
    ->setCheckPermissions(FALSE)
    ->execute()[0];
  if ($event['is_monetary']) {
    return;
  }

  // Check if we're loading the page for a registered person; give a more sensible status message.
  $participantId = $form->getVar('_participantId');

  // Confirm the participant is pending.
  // Note bullshit magic number (see below).
  $participant = \Civi\Api4\Participant::get()
    ->addWhere('status_id', '=', '1')
    ->addWhere('id', '=', $participantId)
    ->setCheckPermissions(FALSE)
    ->execute();
  if (count($participant)) {
    $eventName = $event['title'];
    $statusMsg = ts('You are successfully registered for %1. You can cancel your registration by clicking "Cancel Registration".', [1 => $eventName]);
    $form->assign('statusMsg', $statusMsg);
  }

}

/**
 * Implements hook_civicrm_postProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postProcess/
 */
function fastwaitlist_civicrm_postProcess($formName, &$form) {
  // Is this the waitlist confirmation screen? If not, bye.
  if ($formName != "CRM_Event_Form_Registration_ParticipantConfirm") {
    return;
  }
  // Is this a paid event? If not, bye.
  $paidEvent = \Civi\Api4\Event::get()
    ->addWhere('id', '=', $form->_eventId)
    ->setCheckPermissions(FALSE)
    ->execute()[0]['is_monetary'];
  if ($paidEvent) {
    return;
  }
  $buttonName = $form->controller->getButtonName();

  if ($buttonName == '_qf_ParticipantConfirm_next') {
    $participantId = $form->getVar('_participantId');

    // Confirm the participant is pending
    // This "9" hardcoding (and the "1" below) is bullshit.  API4 doesn't handle status IDs correctly.
    $participant = \Civi\Api4\Participant::get()
      ->addWhere('status_id', '=', '9')
      ->addWhere('id', '=', $participantId)
      ->setCheckPermissions(FALSE)
      ->execute();

    if (count($participant)) {
      // Set the participant to registered.
      $results = \Civi\Api4\Participant::update()
        ->addWhere('id', '=', $participantId)
        ->addValue('status_id', 1)
        ->setCheckPermissions(FALSE)
        ->execute();

      // Send a registration email, which means gathering up all this info about the event and assigning it to Smarty.
      $values['params'][$participantId] = $values['participant'] = $participant[0];
      $values['event'] = \Civi\Api4\Event::get()->addWhere('id', '=', $form->_eventId)->setCheckPermissions(FALSE)->execute()->first();
      // Event Smarty tokens seem to have "event_" prepended.
      foreach ($values['event'] as $key => $value) {
        $newKey = 'event_' . $key;
        $values['event'][$newKey] = $value;
      }
      // LocBlock API not yet in API4.  Nor is there a good way to get every field from a join.
      // $result = civicrm_api3('LocBlock', 'get', [
      //   'sequential' => 1,
      //   'return' => ["address_id.id", "address_id.contact_id", "address_id.location_type_id", "address_id.is_primary", "address_id.is_billing", "address_id.street_address", "address_id.street_number", "address_id.street_number_suffix", "address_id.street_number_predirectional", "address_id.street_name", "address_id.street_type", "address_id.street_number_postdirectional", "address_id.street_unit", "address_id.supplemental_address_1", "address_id.supplemental_address_2", "address_id.supplemental_address_3", "address_id.city", "address_id.county_id", "address_id.state_province_id", "address_id.postal_code_suffix", "address_id.postal_code", "address_id.usps_adc", "address_id.country_id", "address_id.geo_code_1", "address_id.geo_code_2", "address_id.manual_geo_code", "address_id.timezone", "address_id.name", "address_id.master_id", "address_id.world_region"],
      //   'id' => 1,
      // ]);
      // if ($values['event']['loc_block_id']) {
      //   $addressId = civicrm_api3('LocBlock', 'getvalue', ['return' => "address_id", 'id' => $values['event']['loc_block_id']]);
      //   $values['address'] = \Civi\Api4\Address::get()->addWhere('id', '=', $addressId)->setCheckPermissions(FALSE)->execute()->first();
      // }

      $values['custom_pre_id'] = $values['custom_post_id'] = NULL;
      $eventProfiles = \Civi\Api4\UFJoin::get()
        ->addWhere('entity_table', '=', 'civicrm_event')
        ->addWhere('entity_id', '=', $form->_eventId)
        ->setCheckPermissions(FALSE)
        ->execute();
      foreach ($eventProfiles as $profile) {
        if ($profile['weight'] == 1) {
          $values['custom_pre_id'] = $profile['id'];
        }
        if ($profile['weight'] == 2) {
          $values['custom_post_id'] = $profile['id'];
        }
      }

      // Hard-coded: "Registered".  For email.
      $values['participant_status'] = 1;

      $params = [
        'groupName' => 'msg_tpl_workflow_event',
        'valueName' => 'event_online_receipt',
        'contactId' => $participant[0]['contact_id'],
        'isTest' => FALSE,
        'PDFFilename' => 'confirmation.pdf',
        'from' => 197,
        'toName' => 'FIXME',
        'toEmail' => 'FIXME@example.org',
        'cc' => NULL,
        'bcc' => NULL,
      ];
      // Smarty gets everything assigned.
      $smarty = CRM_Core_Smarty::singleton();
      foreach ($params['tplParams'] as $name => $value) {
        $smarty->assign($name, $value);
      }
      CRM_Event_BAO_Event::sendMail($participant[0]['contact_id'], $values, $participantId);

      // CRM_Core_BAO_MessageTemplate::sendTemplate($params);

      $checksumValue = CRM_Contact_BAO_Contact_Utils::generateChecksum($participant[0]['contact_id']);
      $url = CRM_Utils_System::url('civicrm/event/confirm',
        "reset=1&participantId={$participantId}&cs={$checksumValue}"
      );
      CRM_Utils_System::redirect($url);
    }

  }

}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function fastwaitlist_civicrm_config(&$config) {
  _fastwaitlist_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function fastwaitlist_civicrm_xmlMenu(&$files) {
  _fastwaitlist_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function fastwaitlist_civicrm_install() {
  _fastwaitlist_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function fastwaitlist_civicrm_postInstall() {
  _fastwaitlist_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function fastwaitlist_civicrm_uninstall() {
  _fastwaitlist_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function fastwaitlist_civicrm_enable() {
  _fastwaitlist_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function fastwaitlist_civicrm_disable() {
  _fastwaitlist_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function fastwaitlist_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _fastwaitlist_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function fastwaitlist_civicrm_managed(&$entities) {
  _fastwaitlist_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function fastwaitlist_civicrm_caseTypes(&$caseTypes) {
  _fastwaitlist_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function fastwaitlist_civicrm_angularModules(&$angularModules) {
  _fastwaitlist_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function fastwaitlist_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _fastwaitlist_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function fastwaitlist_civicrm_entityTypes(&$entityTypes) {
  _fastwaitlist_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_thems().
 */
function fastwaitlist_civicrm_themes(&$themes) {
  _fastwaitlist_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 *
function fastwaitlist_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 *
function fastwaitlist_civicrm_navigationMenu(&$menu) {
  _fastwaitlist_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _fastwaitlist_civix_navigationMenu($menu);
} // */
