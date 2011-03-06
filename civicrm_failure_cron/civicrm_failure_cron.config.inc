<?php

/*
 * The configuration form builder that saves the configuration settings 
 * as drupal variables
 */
function civicrm_failure_cron_settings() {
  
	if(db_result(db_query("SELECT * FROM civicrm_mailing_bounce_type WHERE id = 1001")) == '') {
		$form['civicrm_failure_cron_install'] = array(
			'#type' => 'markup',
			'#value' => '<div class="messages status">It seems you need to install the bounce type change in CiviCRM, please '.l('Click here', 'admin/civicrm_tools/email_failure_install').' to do it</div>',
		);	
	}
  
	$form['civicrm_failure_cron_toggle'] = array(
		'#type' => 'checkbox',
		'#title' => 'Enable failed email sychronization?',
		'#default_value' => variable_get('civicrm_failure_cron_toggle', 1),
	);
	
  return system_settings_form($form);
}
?>