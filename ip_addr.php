<?php

	// cPanel users check the run function at the bottom of this file.
	
	// Modify these variables:	
	//		$localip
	//		$domain
	// 		$ns1
	// 		$ns2
	// 		cpanel username & hash
	// 		name.com username & api key

	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	require_once('namedotcomapi/vendor/autoload.php');
	require_once('vendor/autoload.php');

	function run($s=false) {

		if($s) {syncDNS(); sleep(2);}

		// Your cPanel server Local / Router IP
		$localip = '127.0.0.1';

		// Your Main Domain name and Nameservers
		$domain = 'host.com';
		$ns1 = 'ns1.host.com';
		$ns2 = 'ns2.host.com';

		// Optional Static IPv6
		$v6 = '123';

		try {

			if ($s) {
				// WHM API key
				$cpanel = new \Gufy\CpanelPhp\Cpanel([
				    'host'        =>  'https://'.$localip.':2087',
					'username'    =>  'root',
					'auth_type'   =>  'hash',
					'password'    =>  'apikeyxxxxxxxxxxxxxxxxxxxx'
				]);
			}

			// Name.com API Key
			$name = new NameDotComApi('user', 'apikeyxxxxxxxxxxxxxxxxxxx', 0);

		} catch ( Exception $e ) {
			print_r($e);
		}



		/**
		* NS Changes
		**/

		$p = grabip();
		$nameserverA = $name->GetVanityNameserver($domain, $ns1);
		$nameserverB = $name->GetVanityNameserver($domain, $ns2);

		changeNS( $name, $nameserverA, $domain, $ns1, $p ); 
		changeNS( $name, $nameserverB, $domain, $ns2, $p ); 



		/**
		* CPanel Changes
		**/
		sleep(3);
		if ($s) {
	
			fix_cpanel_dns($cpanel, $p, $ns1, $ns2, $domain, $localip);
			
			$cpanel->nat_set_public_ip([
				'local_ip' => $localip,
				'public_ip' => $p
			]);

			$cpanel->set_tweaksetting([
				'key' => 'ipaddress',
				'value' => $p
			]);

			sleep(2);
			syncDNS();
		}


	}



	function changeNS( $name, $nameserverA, $domain, $ns1, $p ) {
		if ($nameserverA == false) {
			print_r('Nameserver sync failed.');
			return false;
		} else {
			foreach ($nameserverA as $key => $ip) {

				// If the IP does not match 'p', the currenct external IP,
				// recreate the nameservers to re-point the DNS satellites to ourselves.

				if ($ip != $p) {

					// $remove = $name->DeleteVanityNameserver( $domain, $ns1 );
					// $create = $name->CreateVanityNameserver( $domain, $ns1, $p );
					$update = $name->UpdateVanityNameserver( $domain, $ns1, $p );
					print_r($update);
					print_r('Updating '.$ns1.' from '.$ip.' to '.$p." IPv4 Address. \n");
				} else {
					print_r($ns1.' up to date: '.$ip."\n");
				}

			}
		}
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
			'pilot'
		];

		$allZones = json_decode($cpanel->listzones());

		foreach ($allZones->zone as $j => $z) {

			$siteip = $cpanel->setsiteip([
				'domain' => $z->domain, 
				'ip' => $localip
			]);

			$zones = json_decode($cpanel->dumpzone(['domain' => $z->domain]));

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
							$newzone = json_decode($cpanel->editzonerecord($data));
							if( !$newzone->result[0]->status ) {
								print_r( $data );
								print_r( $newzone );
							}
						} catch ( Exception $e ) {
							print_r( $data );
						}
						
					} else if ( isset($zone->name) && $zone->type == 'A' && strpos($zone->name.'.'.$z->domain, $label) !== false ) {

						$address = $primary;
						// if ( $zone->name == 'ns1.'.$domain.'.' ) $address = $ns1;
						// if ( $zone->name == 'ns1' ) $address = $ns1;
						// if ( $zone->name == 'ns2.'.$domain.'.' ) $address = $ns2;
						// if ( $zone->name == 'ns2' ) $address = $ns2;

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
							$newzone = json_decode($cpanel->editzonerecord($data));
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
							$newzone = json_decode($cpanel->editzonerecord($data));
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
		print_r('Syncronizing DNS... '."\n");
		exec("/scripts/dnscluster syncall");
	}

	/****
	* Get the Current IP Address
	*****/
	function grabip() {
		$request = Requests::get('https://ip.seeip.org/jsonip?');
		$data = json_decode($request->body, TRUE);
		return $data['ip'];
	}


	// When running on a Cpanel Server, change argument to: true
	run(true); 


