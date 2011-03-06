<?php

/*
 * The configuration form builder that saves the configuration settings 
 * as drupal variables
 */
function civicrm_spam_cron_settings() {

 $form['socketlabs'] = array(
	'#type' => 'fieldset',
	'#title' => t('Socketlabs API Settings'),
	'#description' => t('API settings for Socketlabs'),
	'#collapsible' => TRUE,
	'collapsed' => FALSE,
  );
  
  $form['socketlabs']['civicrm_spam_cron_socketlabs_user'] = array(
	'#type' => 'textfield',
	'#title' => t('User Account/Name'),
	'#description' => t('User account name in socketlabs'),
	'#default_value' => variable_get('civicrm_spam_cron_socketlabs_user', 'user12345'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['socketlabs']['civicrm_spam_cron_socketlabs_key'] = array(
	'#type' => 'textfield',
	'#title' => t('API Key'),
	'#description' => t('Key for API access'),
	'#default_value' => variable_get('civicrm_spam_cron_socketlabs_key', 'abcdefghijklmn'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['socketlabs']['civicrm_spam_cron_socketlabs_account'] = array(
	'#type' => 'textfield',
	'#title' => t('Account Number'),
	'#description' => t('The all digit account number, i.e. 2042'),
	'#default_value' => variable_get('civicrm_spam_cron_socketlabs_account', '2042'),
	'#required' => TRUE,
  );
  
  $form['socketlabs']['civicrm_spam_cron_date_range'] = array(
	'#type' => 'select',
	'#title' => t('Lookup date range'),
	'#description' => t('Date Range for synchronizing spam complaints'),
	'#default_value' => variable_get('civicrm_spam_cron_date_range', '3'),
	'#options' => array(
		'1' => t('1 Day (current day)'),
		'2' => t('2 Days (past 2 days)'),
		'3' => t('3 Days (past 3 days'),
		'4' => t('4 Days (past 4 days'),
		'5' => t('5 Days (past 5 days'),
		'6' => t('6 Days (past 6 days'),
		'7' => t('7 Days (past 7 days'),
	),
  );
  
  return system_settings_form($form);
}
?>