<?php

require_once('sites/all/modules/civicrm_tools/civicrm_tools.class.php');

class civicrm_failure_cron extends civicrm_tools {
	
	private $socketlabs_user;
	private $socketlabs_apikey;
	private $socketlabs_account;
	
	public function __construct($socketlabs_user, $socketlabs_apikey, $socketlabs_account) {
		// initialize CiviCRM APIs
		parent::__construct();
		$this->socketlabs_user = $socketlabs_user;
		$this->socketlabs_account = $socketlabs_account;
		$this->socketlabs_apikey = $socketlabs_apikey;
	}
	
	public function __get($variable) {
		return $this->$variable;
	}
	
	public function __set($variable, $value) {
		$this->$variable = $value;
	}
	
	/*
	 * Run the synchronization process
	 */
	public function run($size = 500, $date_range = 1) {
		
		// drupal API (V6)
		civicrm_initialize();
		require_once('api/v2/Group.php');
		require_once('api/v2/GroupContact.php');

		$options = array(
			'type' => 'json',
			'sortDirection' => 'asc',
		);
		
		return $this->api_failures($options, $size, $date_range);		

	}

	
	/*
	 ****************************************************
	 * Processes failed email records specified
	 * create them
	 *
	 * @params
	 * array $options		key:	socketlabs API query varialeble
	 *						value:	socketlabs API query variable value
	 * int $size			size of the query (multiple of 500)
	 *
	 * @return
	 * mixed				null if everything processed fine
	 *						string if some warning or error has occured
	 *
	 */	
	private function api_failures($options = array(
										'type' => 'json',
										'sortDirection' => 'asc',
									), $size = 500, $date_range = 1) {
		
		// populate the API options
		foreach($options as $param => $value) {
			$_options .= '&'.$param.'='.$value;
		}

		// use processed to determine if we still need to run the sync
		$processed = db_result(db_query("SELECT processed FROM {civicrm_failure_cron} WHERE date = '%s'", date('Y-m-d')));
		if($processed == '') {
			@db_query("INSERT INTO {civicrm_failure_cron} (`date`) VALUES ('%s')", date('Y-m-d'));
			$processed == 0;
		} else {
			// increment processed by one because we will use this to start the next index in the API call
			$processed+=1;
		}
		$start = $processed;
		
		$service_uri = sprintf("https://api.socketlabs.com/v1/messagesFailed?accountId=%s&disposition=0&startDate=%s&endDate=%s&timeZone=-360&index=%d%s",
			$this->socketlabs_account,
			date('Y-m-d', time() - ($date_range * 86400)),
			date('Y-m-d'). '%2023:59:59',
			$start,
			$_options
		);
		
		// print_r($service_uri);
		
		// issue the request
		$ch = curl_init($service_uri);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->socketlabs_user . ':' . $this->socketlabs_apikey);
		$res = curl_exec($ch);
		curl_close($ch);
		
		
		// $res = addslashes($res);
		if($res) {
			$_res = json_decode($res, TRUE);
			
			// if($processed >= $_res['totalCount']) {
				// return 'No new failure records to fetch';
			// }
			if($_res['count'] == 0) {
				return 'No new failure records to fetch';
			}
			
			// process this 500 records
			$processed += $this->process_records($_res);
		}		
		
		// Update the new (run) and new (processed)
		$run = db_result(db_query("SELECT run FROM {civicrm_failure_cron} WHERE date = '%s'", date('Y-m-d')));
		@db_query("UPDATE {civicrm_failure_cron} SET run = %d, processed = %d WHERE `date` LIKE '%s%%'", $run+1, $processed, date('Y-m-d')); //@
		
		
		return NULL;	
	}

	/*
	 ****************************************************
	 * Process 500 (1) index page of failure records
	 *
	 * @params
	 * array $result_set
	 *	Array
	 *	(
	 *		[timestamp] => 1.28587332846E+12
	 *		[totalCount] => 6199
	 *		[count] => 500
	 *		[collection] => Array
	 *			(
	 *				[0] => Array
	 *					(
	 *						[AccountId] => 2438
	 *						[DateTime] => 1.285742027E+12
	 *						[MessageId] => 
	 *						[MailingId] => www(DOT)haleyspac(DOT)com-11
	 *						[ToAddress] => gboodaghian@aol.com
	 *						[FromAddress] => 
	 *						[FailureType] => 0
	 *						[FailureCode] => 3004
	 *						[Reason] => 501 5.1.7 Bad sender address syntax In response to the MAIL FROM command.
	 *					)
	 *			)
	 *	)
	 *
	 * @return
	 * int $processed		Number of records processed
	 */	
	private function process_records($result_set = array()) {
		
		require_once('CRM/Core/DAO.php');
		require_once('api/v2/GroupContact.php');
		
		$fail_groups = $this->groups_check();
		$soft_fail_params = array('group_id' => $fail_groups['email_fails_soft']);
		$hard_fail_params = array('group_id' => $fail_groups['email_fails_hard']);
		$x = 0;
		$y = 1;
		$z = 1;
		
		// for old conversion stuff
		$old_server_host = str_replace('.', '(DOT)', $_SERVER['HTTP_HOST']);
		$server_host = str_replace('.', 'DOT', $_SERVER['HTTP_HOST']);
		
		for($i = 0; $i<count($result_set['collection']); $i++) {
		
			// We make sure the we are only dealing with emails on the particular 
			// Domain
			if(stristr($result_set['collection'][$i]['MailingId'], $server_host) || stristr($result_set['collection'][$i]['MailingId'], $old_server_host)) {
				//1 find the contact
				//Bug: May come back with multiple contacts but we are using singleValueQuery here
				$query = sprintf("SELECT contact_id FROM civicrm_email WHERE email = '%s'", addslashes($result_set['collection'][$i]['ToAddress']));
				$contact_id = CRM_Core_DAO::singleValueQuery($query);
				
				if($contact_id != NULL) {
					if($result_set['collection'][$i]['FailureType'] == 0) {
						$soft_fail_params['contact_id.'.$y] = $contact_id;
						$y++;
					} else {
						$hard_fail_params['contact_id.'.$z] = $contact_id;
						$z++;
					}
					// add a bounce record
					$mailing_id = substr($result_set['collection'][$i]['MailingId'], strrpos($result_set['collection'][$i]['MailingId'], '-')+1);
					$this->add_bounce_record($mailing_id, 
						addslashes($result_set['collection'][$i]['ToAddress']), 
						$result_set['collection'][$i]['FailureCode'],
						addslashes($result_set['collection'][$i]['Reason'])
					);
				}
				// $emails[] = $result_set['collection'][$i]['ToAddress'];
			}
			$x++;
		}
		
		// $_emails = implode('
// ', $emails);
		
		//temp
		// file_put_contents('/var/www/sites/default/files/emails.txt', $_emails, FILE_APPEND);
		
		// adding to group
		// @civicrm API (v2)
		$res1 = civicrm_group_contact_add( $hard_fail_params );
		$res2 = civicrm_group_contact_add( $soft_fail_params );
		
		// return how many records processed
		return $x;
	}
	
	/*
	 ****************************************************
	 * Add a bounce record into the CiviCRM database
	 * 
	 * @params
	 * int $mailing_id			CiviCRM mailing id
	 * string $email			The email address that has failed
	 * int $bounce_type_id		The bounce type id, should correspond to the list
	 *							of the socketlabs code
	 * string $bounce_reason	The reason for the bounce
	 *
	 * @return
	 *
	 */	
	private function add_bounce_record($mailing_id = null, $email = null, $bounce_type_id = 9999, $bounce_reason = 'unknown') {
		
		require_once('CRM/Core/DAO.php');
		
		// if we don't have what we want, just skip this record
		if($mailing_id == null || $email == null || !is_numeric($mailing_id)) {
			return;
		}	
		
		$event_queue_id_query = sprintf("SELECT civicrm_mailing_event_queue.id 
			FROM civicrm_mailing_event_queue 
				JOIN civicrm_mailing_job ON 
				civicrm_mailing_event_queue.job_id = civicrm_mailing_job.id
				JOIN civicrm_email ON
				civicrm_email.id = civicrm_mailing_event_queue.email_id
			WHERE civicrm_mailing_job.mailing_id = %d AND civicrm_email.email = '%s'
			LIMIT 0, 1", $mailing_id, $email);
		
		$event_queue_id = CRM_Core_DAO::singleValueQuery($event_queue_id_query);
		
		// check to see if this queued email has already been added to bounce list
		if(isset($event_queue_id)) {
			$check = CRM_Core_DAO::singleValueQuery(sprintf("SELECT id FROM civicrm_mailing_event_bounce WHERE event_queue_id = %d", $event_queue_id));
		}
		
		// only insert the bounce record if it doesn't exist
		if($check == NULL || !$check || $check === '') {
			// Assume the SQL alter has been added, we can use those bounce type id directly
			$query = "INSERT INTO civicrm_mailing_event_bounce (event_queue_id, bounce_type_id, bounce_reason, time_stamp) 
			VALUES (%1,%2,%3,%4)";
			
			$params = array(
				1 => array($event_queue_id, 'Integer'),
				2 => array($bounce_type_id, 'Integer'),
				3 => array($bounce_reason, 'String'),
				4 => array(date('Y-m-d H:i:s'), 'String'),
			);
			
			CRM_Core_DAO::executeQuery($query, $params);		
		}
	
	}
	
	/*
	 ****************************************************
	 * Check to see if the fail groups exists, if not 
	 * create them
	 *
	 * @params
	 * null
	 *
	 * @return
	 * array $data			key:	email_fails_hard	|	value: id of the group
	 *						key:	email_fails_soft	|	value: id of the group
	 *
	 */
	private function groups_check() {
		
		$data = array();
		
		$names = array(
			'email_fails_hard',
			'email_fails_soft',
		);
		
		foreach($names as $name) {
			$params = array(
				'name' => $name,
			);
			
			$result = civicrm_group_get($params);
			if($result['is_error'] == 1) {
				$group_id = $this->_create_group($name);
				$data[$name] = $group_id;
			} else {
				foreach($result as $group) {
					$data[$name] = $group['id'];
				}
			}
		}
			
		return $data;
	}
	
	public function put_onhold() {

		// CRM_Core_DAO::query($query);
		@db_query("UPDATE   civicrm_email ce
			INNER JOIN   civicrm_contact c ON ( c.id = ce.contact_id )
			LEFT JOIN   civicrm_group_contact gc ON ( gc.contact_id = c.id and gc.status = 'Added' )
			INNER JOIN   civicrm_group gr ON ( gr.id = gc.group_id )
			SET   ce.on_hold = 1
			WHERE   gr.name = 'email_fails_hard'");
	}
	
} // end of class
?>