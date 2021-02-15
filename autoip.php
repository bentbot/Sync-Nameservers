require_once './vendor/autoload.php';
Requests::register_autoloader();

$username = '--------'; // Name.com API Username and token password.
$token = '----------------------------------';
$domain = '**********.com'; // Root domain name of nameserver scheme.
$hostname = 'ns1.**********.com'; // Set this to NS1 top-level domain name.
$hostname2 = 'ns2.**********.com'; //Set this to NS2.toplevel.com
$processNS1 = true; // True if this is the only  /  NS1 server.
$processNS2 = true; // True if this also the NS2 server.

$json = Requests::get('https://ip.seeip.org/jsonip?');
$my_ip = json_decode($json->body, TRUE);
$ip = $my_ip['ip'];

// Name.com API	
$name = new NameDotComApi($username, $token);
if ( $processNS1 && $resulta = $name->UpdateVanityNameserver($domain, $hostname, [$ip]) ) {

  if($resulta['ips'][0]) {
    print_r('Name.com '.$hostname.' changed to: '.$resulta['ips'][0]."\n"); sleep(1);
  } else { print_r('Sorry, Error connecting to Name.com'); }
  
  if ( $processNS2 && $resultb = $name->UpdateVanityNameserver($domain, $hostname2, [$ip]) ) {
    if($resultb['ips'][0]) {
      print_r('Name.com '.$hostname2.' changed to: '.$resultb['ips'][0]."\n");
    } else { print_r('Sorry, Error connecting to Name.com'); }
  }
}
