<?php

class civicrm_tools {

	public function __construct() {
		civicrm_initialize();
		
		require_once('CRM/Core/Config.php');
		require_once('CRM/Core/DAO.php');
		$config =& CRM_Core_Config::singleton();
		
		// CiviCRM APIs
		require_once('api/v2/Contact.php');
		require_once('api/v2/Group.php');
		require_once('api/v2/GroupContact.php');		
	}

	public static function _create_group($group_title) {
	
		civicrm_initialize();
		require_once('api/v2/Group.php');
		
		$strip = array('!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '+', '=', ';', ',', '/', '?', '<', '>', ' ');
		$group_name = strtolower(str_replace($strip, '_', $group_title));
		
		$params = array(
			'name'        => $group_name,
			'title'       => $group_title,
			'description' => '',
			'is_active'   => 1,
			'visibility'  => 'User and User Admin Only',
			'group_type'  => array( '1' => 1, '2' => 1 ),
		);

		$result = &civicrm_group_add( $params );
		if ( civicrm_error ( $result )) {
			return false;
			//return $result['error_message'];
		} else {
			return $result['result'];
		}
	}
}

?>