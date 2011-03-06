<?php

require_once('sites/all/modules/civicrm_tools/civicrm_tools.class.php');

class civicrm_spam_cron extends civicrm_tools {
	
	private $socketlabs_account;
	private $socketlabs_apikey;
	private $socketlabs_account_number;
	
	public function __construct($socketlabs_account, $socketlabs_apikey, $socketlabs_account_number) {
		# initialize CiviCRM APIs
		parent::__construct();
		$this->socketlabs_account = $socketlabs_account;
		$this->socketlabs_apikey = $socketlabs_apikey;
		$this->socketlabs_account_number = $socketlabs_account_number;
	}
	
	public function run($date_range = '1') {
		civicrm_initialize();
		require_once('api/v2/Contact.php');
		require_once('CRM/Core/DAO.php');
	
		$data = $this->_prepare($date_range);
		
		// var_dump($data);
		
		if(empty($data) || count($data) == 0) {
			return false;
		}
		
		$errors = 0;
		for($i=0;$i<count($data);$i++) {
			$params = array(
				'contact_id' => $data[$i],
				'do_not_email' => 1,
				'contact_type' => 'Individual',
			);
			
			$result = civicrm_contact_update($params);
			if ( civicrm_error ( $result )) {
				// echo $result['error_message'];
				$errors++;
			}
			CRM_Core_DAO::freeResult();
		}
		
		if($errors > 0) {
			return false;
		} else {
			return true;
		}
	}
	
	private function _prepare($date_range) {
		
		$data = array();
		
		// when testing, create a dummy $_rests
		$date = date('Y-m-d', strtotime('-' . $date_range));
		
		$request_url = sprintf("https://api.socketlabs.com/v1/messagesFblReported?accountId=%s&disposition=0&type=json&sortDirection=desc&startDate=%s",
								$this->socketlabs_account_number, //substr($this->socketlabs_account, 4),
								$date);

		$curl = curl_init($request_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERPWD, $this->socketlabs_account . ':' . $this->socketlabs_apikey);
		$results = curl_exec($curl);
		curl_close($curl);
		
		if($results) {
			
			$_results = json_decode($results, true);			
			
			// Chang is here, dummy testing
			// $_results = array(
				// 'count' => 3,
				// 'collection' => array(
					// array(
						// 'OriginalRecipient' => 'convallis@euenimetiam.org',
						// 'MailingId' => 'localhost',
					// ),
					// array(
						// 'OriginalRecipient' => 'donec@vulputatelacuscras.com',
						// 'MailingId' => 'localhost',
					// ),
					// array(
						// 'OriginalRecipient' => 'suspendisse.ac.metus@quispede.ca',
						// 'MailingId' => 'localhost',
					// ),
					// array(
						// 'OriginalRecipient' => 'facilisis@dignissimmagnaa.org',
						// 'MailingId' => 'someimaginary(DOT)com',
					// ),
				// ),
			// );
		
			if($_results['count'] == 0) {
				return 'No email address needs to be synched';
			} else {
				
				# for backward compitability
				$old_server_host = str_replace('.', '(DOT)', $_SERVER['HTTP_HOST']);
				$server_host = str_replace('.', 'DOT', $_SERVER['HTTP_HOST']);
				
				for($i=0;$i<count($_results['collection']);$i++) {
					
					// use the Mailing Id to determine which client this is from
					if(stristr($_results['collection'][$i]['MailingId'], $server_host) || stristr($_results['collection'][$i]['MailingId'], $old_server_host)) {
					
						// echo $_results['collection'][$i]['OriginalRecipient'] . ' ';
					
						$query = sprintf("SELECT e.contact_id FROM civicrm_email e
											JOIN civicrm_contact c ON e.contact_id = c.id
											WHERE e.email = '%s' AND c.do_not_email = %d",
										$_results['collection'][$i]['OriginalRecipient'],
										0);
										
						// search to see if a contact with the email exist and it is not currently on do not email
						$contact_id = CRM_Core_DAO::singleValueQuery($query);
						
						// echo $contact_id . '<br />';
						
						if($contact_id != NULL) {
							$data[] = $contact_id;
						}
					}	
				}
				return $data;
			}	
		} else {
			return FALSE;
		}	
	}
}
?>