<?php

	/**
	* 		File used to sync Name.com Nameservers & cPanel DNS records.
	* 		liam@hogan.re - 2019
	**/


	error_reporting(E_ALL);
	ini_set('display_errors', 1);

	require_once(dirname(__FILE__).'/namedotcomapi/vendor/autoload.php');
	require_once(dirname(__FILE__).'/vendor/autoload.php');

	function run($s='') {

		syncDNS();

		// Your cPanel server Local / Router IP 
		$localip = '10.0.10.50';
		
		// Your Main Domain name and Nameservers
		$domain = 'janglehost.com';
		$ns1 = 'ns1.janglehost.com';
		$ns2 = 'ns2.janglehost.com';

		// Optional Static IPv6
		$v6 = '123';

		try {

			// WHM API key
			$cpanel = new \Gufy\CpanelPhp\Cpanel([
			    'host'        =>  $localip.':2087',
				'username'    =>  'root',
				'auth_type'   =>  'hash',
				'password'    =>  '$HASHCODE'
			]);

			// Name.com API Key
			$name = new NameDotComApi('$USERNAME', '$APICODE');

		} catch ( Exception $e ) {
			print_r($e);
		}



		/** 
		* DONT CHANGE ANYTHING BELOW THIS LINE...
		**/

		$p = grabip();

		$nameserverA = $name->GetVanityNameserver($domain, $ns1);
		$nameserverB = $name->GetVanityNameserver($domain, $ns2);

		if ($nameserverA == false) {
			print_r('NameserverA sync failed.');
			return false;
		} else { 
			foreach ($nameserverA as $key => $ip) {
				if ($ip != $p) {
					$update = $name->UpdateVanityNameserver( $domain, $ns1, $p );
					print_r('Updated NS1 to '.$p."... \n");
					print_r($update);
				} else {
					print_r('NS1 up to date... '."\n");
				}
			}
		}


		if ($nameserverB == false) {
			print_r('NameserverB sync failed.');
			return false;
		} else { 
			foreach ($nameserverB as $key => $ip) {
				if ($ip != $p) {
					$update = $name->UpdateVanityNameserver( $domain, $ns2, $p );
					print_r('Updated NS2 to '.$p."... \n");
					print_r($update);
				} else {
					print_r('NS2 up to date... '."\n");
				}
			}
		}

		$ns1 = $p;
		$ns2 = $p;

		$cpanel->nat_set_public_ip([
			'local_ip' => $localip,
			'public_ip' => $p
		]);

		$cpanel->set_tweaksetting([
			'key' => 'ipaddress',
			'value' => $p
		]);

		
		print_r('Updating DNS entries... '."\n");
		fix_cpanel_dns($cpanel, $p, $ns1, $ns2, $domain, $localip);
		sleep(1);
		print_r('Syncronizing DNS... '."\n");
		syncDNS();
	}


	function set_site_ip($domain, $ip) {
		try {
			$ipaddr = $cpanel->dumpzone([ 
				'domain' => $domain,
				'ip' => $ip
			]);
		} catch ( Exception $e ) {
			print_r( $e );
		}

		return $ipaddr;
	}

	/****
	* Run through all domain names in the DNS server and re-assign new
	* static IP address and nameserver values based on parameters.
	*****/

	function fix_cpanel_dns($cpanel, $primary, $ns1, $ns2, $domain, $localip) {

		$globalTTL = 300;

		// DNS Labels to update with current IP addresses
		$dns_labels = [
			'ftp',
			'cpcontacts',
			'whm',
			'cpcalendars',
			'webmail',
			'cpanel',
			'webdisk',
			'ns1',
			'ns2'
      //  YOU CAN ADD ADDITIONAL DNS "A" RECORDS TO THE EXTERNAL IP ADDRESS HERE.
		];


		$allZones = $cpanel->listzones();

		foreach ($allZones->zone as $j => $z) {

			set_site_ip($z->domain, $primary);

			$zones = $cpanel->dumpzone(['domain' => $z->domain]);
			$zones = $zones->result[0]->record;

			foreach ($dns_labels as $h => $label) {
				foreach ($zones as $k => $zone) {

					if ( isset($zone->name) && $zone->type == 'A' && ($zone->name == $z->domain.'.') ) {
						$data = [
							'ttl' 	 => ($globalTTL) ? $globalTTL : $z->ttl,
							'domain' => $z->domain,
							'line' => 	$zone->Line,
							'name' => 	$zone->name,
							'class'=> 	$zone->class,
							'address'=>	$primary,
							'type'=> 	$zone->type,
						];
						try {
							$newzone = $cpanel->editzonerecord($data);
							if( !$newzone->result[0]->status ) {
								print_r( $data );
								print_r( $newzone );
							}
						} catch ( Exception $e ) {
							print_r( $data );
						}
						
					} else if ( isset($zone->name) && $zone->type == 'A' && strpos($zone->name.'.'.$z->domain, $label) !== false ) {

						$address = $primary;
						if ( $zone->name == 'ns1.'.$domain.'.' ) $address = $ns1;
						if ( $zone->name == 'ns2.'.$domain.'.' ) $address = $ns2;

						$data = [
							'ttl' 	 => ($globalTTL) ? $globalTTL : $z->ttl,
							'domain' => $z->domain,
							'line' => 	$zone->Line,
							'name' => 	$zone->name,
							'class'=> 	$zone->class,
							'address'=>	$address,
							'type'=> 	$zone->type,
						];

						try {
							$newzone = $cpanel->editzonerecord($data);
							if( !$newzone->result[0]->status ) {
								print_r( $data );
								print_r( $newzone );
							}
						} catch ( Exception $e ) {
							print_r( $data );
						}
						
					} else if ( isset($zone->name) && $zone->type == 'TXT' && ($zone->name == $z->domain.'.') && strpos($zone->txtdata, 'v=spf1') !== false ) { 

						$newData = '"v=spf1 ip4:'.$primary.' ip4:'.$localip.' +a +mx ~all"';

						$data = [
							'domain' => $z->domain,
							'line' => 	$zone->Line,
							'type'=> 	$zone->type,
							'name' => 	$zone->name,
							'ttl' 	 => 14400,
							'class'=> 	$zone->class,
							'txtdata'=>	$newData
						];

						try {
							$newzone = $cpanel->editzonerecord($data);
							if( !$newzone->result[0]->status ) {
								print_r( $data );
								print_r( $newzone );
							}
						} catch ( Exception $e ) {
							print_r( $data );
						}

					}
				}
			}
		}

		return;
	
	}



	/****
	* Server level function designed to sync all DNS servers in cluster.
	*****/
	function syncDNS() {
		exec("/scripts/dnscluster syncall");
	}

	/****
	* Use an API to grab the external IP address.
	*****/
	function grabip() {
		$request = Requests::get('https://ip.seeip.org/jsonip?');
		$data = json_decode($request->body, TRUE);
		return $data['ip'];
	}


	run(' ;) ');
