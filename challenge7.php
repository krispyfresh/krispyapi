<?php

namespace OpenCloud;

// Author: Chris Parsons

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

//include the rackspace library
require('rackspace.php');

$SERVERPREFIX = 'web'; //will create two servers, $SERVERPREFIX1 and $SERVERPREFIX2
$OS = 'CentOS 6.3'; //OS for both cloud servers
$MEMORY = 512; //memory for both cloud servers
$LBNAME = "KrispyLB";

//read the ini file and store it in $ini as an array
//ini file is at .rackspace_cloud_credentials and contains:
//[authentication]
//username: $your_username
//apikey: $your_apikey
$ini = parse_ini_file(".rackspace_cloud_credentials", TRUE);

//get an auth token by passing the username and api key to the auth server
$auth = array('username' => $ini['authentication']['username'],
              'apiKey' => $ini['authentication']['apikey']);
$rsconnect = new Rackspace(RACKSPACE_US, $auth);

//create a handle to cloud servers
$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', 'DFW');

$image = $cloudservers -> ImageList(FALSE,array('name' => $OS)) -> Next();

//$flavor = $cloudservers -> FlavorList(FALSE,array('ram' => 2048)) -> Next();
$flavorlist = $cloudservers -> FlavorList();
while($f = $flavorlist -> Next())
{
    //print($f->name."\n");
    if($f -> ram == $MEMORY)
    {
        $flavor = $f;
    }
}

print("Creating "."$SERVERPREFIX"."1...\n");
$server1 = $cloudservers -> Server();
$response = $server1 -> Create(array('name' => $SERVERPREFIX."1",
                        'image' => $image,
                        'flavor' => $flavor));

//$server1 -> WaitFor("COMPLETE", 300);

print("Creating "."$SERVERPREFIX"."2...\n");
$server2 = $cloudservers -> Server();
$response = $server2 -> Create(array('name' => $SERVERPREFIX."2",
                        'image' => $image,
                        'flavor' => $flavor));

$server2 -> WaitFor("COMPLETE", 300);

$server1data = objecttoArray($server1); //transform object data into an array for easy reading by index
$server2data = objecttoArray($server2); //transform object data into an array for easy reading by index
//sleep(300);

//print(var_dump(get_object_vars($server1))."\n");
$cloudlb = $rsconnect -> LoadBalancerService('cloudLoadBalancers','DFW');

print("Creating load balancer $LBNAME...\n");
$loadbalancer = $cloudlb -> LoadBalancer();
print($server1data['addresses']['private'][0]['addr']."\n");
print($server1data['addresses']['private'][0]['addr']."\n");

$loadbalancer -> AddNode($server1data['addresses']['private'][0]['addr'], 80);
$loadbalancer -> AddNode($server2data['addresses']['private'][0]['addr'], 80);
//$loadbalancer -> AddNode('1.1.1.1', 80);
//$loadbalancer -> AddNode('2.2.2.2', 80);
$loadbalancer -> AddVirtualIp('public');
$response = $loadbalancer -> Create(array('name' => $LBNAME,
                                          'protocol' => 'HTTP',
                                          'port' => 80));
$loadbalancer -> WaitFor("COMPLETE", 300);

//shamelessly stole this code from google because i can't find a way to
//show the cloud server private IP via a method or variable
//this will convert an object to an array so i an read values by index
//hopefully this fix is temporary!
function objectToArray($d)
{
    if (is_object($d))
    {
	// Gets the properties of the given object
	// with get_object_vars function
	$d = get_object_vars($d);
    }
    if (is_array($d)) {
	/*
	* Return array converted to object
	* Using __FUNCTION__ (Magic constant)
	* for recursive call
	*/
	return array_map(__FUNCTION__, $d);
    }
    else {
	// Return array
	return $d;
    }
}






?>