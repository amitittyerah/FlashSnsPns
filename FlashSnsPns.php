<?php

	/**
	 * Flash_sns_pns class.
	 */
	 
	class FlashSnsPns 
	{
		// Class variable
		private $protocol = "https://";
		
		private $access_key;
		private $secret_key;
		private $region;
		
		// Endpoint list
		private $endpoints = array(
			'US-EAST-1'  => 'sns.us-east-1.amazonaws.com',
			'US-WEST-1'  => 'sns.us-west-1.amazonaws.com',
			'US-WEST-2'  => 'sns.us-west-2.amazonaws.com',
			'EU-WEST-1'  => 'sns.eu-west-1.amazonaws.com',
			'AP-SE-1'    => 'sns.ap-southeast-1.amazonaws.com',
			'AP-NE-1'    => 'sns.ap-northeast-1.amazonaws.com',
			'SA-EAST-1'  => 'sns.sa-east-1.amazonaws.com'
		);
		
		public function __construct($access_key = '', $secret_key = '', $region = '')
		{
			if($access_key == '' || $secret_key == '' || $region == '') {
				die(__CLASS__.": Access, Secret or Region is required.");
			}
			
			$this->access_key = $access_key;
			$this->secret_key = $secret_key;
			
			if(!array_key_exists(strtoupper($region), $this->endpoints)) {
				die(__CLASS__.": Incorrect region code.");
			}
			
			$this->region = $region;
		}
		
		// -----------------------------------------------------------
		
		/**
		 * createUrl function.
		 * 
		 * @access public
		 * @param mixed $action
		 * @param array $params (default: array())
		 * @return void
		 */
		function createUrl($action, $params = array())
        {
	        // Add in required params
			$params['Action'] = $action;
			$params['AWSAccessKeyId'] = $this->access_key;
			$params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
			$params['SignatureVersion'] = 2;
			$params['SignatureMethod'] = 'HmacSHA256';
	
			// Sort and encode into string
			uksort($params, 'strnatcmp');
			$queryString = '';
			foreach ($params as $key => $val) {
				$queryString .= "&{$key}=".rawurlencode($val);
			}
			$queryString = substr($queryString, 1);
	
			// Form request string
			
			$endpoint =  $this->endpoints[strtoupper($this->region)];
			
			$requestString = "GET\n"
				. $endpoint."\n"
				. "/\n"
				. $queryString;
	
			// Create signature - Version 2
			$params['Signature'] = base64_encode(
				hash_hmac('sha256', $requestString, $this->secret_key, true)
			);
	
			// Finally create request
			$request = $this->protocol . $endpoint . '/?' . http_build_query($params);
			
			return $request;
        }
        
		// -----------------------------------------------------------
		
		/**
		 * multiRequest function.
		 * 
		 * @access public
		 * @param mixed $data
		 * @param array $options (default: array())
		 * @return void
		 */
		public function multiRequest($data, $options = array()) 
		{
			// array of curl handles
			$curly = array();
			
			// data to be returned
			$result = array();
			
			// multi handle
			$mh = curl_multi_init();
			
			// loop through $data and create curl handles
			// then add them to the multi-handle
			foreach($data as $id => $d) 
			{
			
				$curly[$id] = curl_init();
			
				$url = (is_array($d) && !empty($d['url'])) ? $d['url'] : $d;
				curl_setopt($curly[$id], CURLOPT_URL,            $url);
				curl_setopt($curly[$id], CURLOPT_HEADER,         0);
				curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);
			
				// post?
				if(is_array($d)) 
				{
					if(!empty($d['post'])) 
					{
						curl_setopt($curly[$id], CURLOPT_POST, 1);
						curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
					}
				}
			
				// extra options?
				if (!empty($options)) 
				{
					curl_setopt_array($curly[$id], $options);
				}

				curl_multi_add_handle($mh, $curly[$id]);
			}
			
			// execute the handles
			$running = null;

			do {
				curl_multi_exec($mh, $running);
			} while($running > 0);
			
			
			// remove handles
			foreach($curly as $id => $c) 
			{
				curl_multi_remove_handle($mh, $c);
			}
			
			// all done
			curl_multi_close($mh);
	    }
	}

?>