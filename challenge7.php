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

//create a handle to Cloud Servers
$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', 'DFW');

$image = $cloudservers -> ImageList(FALSE,array('name' => $OS)) -> Next();

$flavorlist = $cloudservers -> FlavorList();
while($f = $flavorlist -> Next())
{
    if($f -> ram == $MEMORY)
    {
        $flavor = $f;
    }
}

//create both cloud servers
print("Creating "."$SERVERPREFIX"."1 and $SERVERPREFIX"."2...\n");
$server1 = $cloudservers -> Server();
$response = $server1 -> Create(array('name' => $SERVERPREFIX."1",
                        'image' => $image,
                        'flavor' => $flavor));

$server2 = $cloudservers -> Server();
$response = $server2 -> Create(array('name' => $SERVERPREFIX."2",
                        'image' => $image,
                        'flavor' => $flavor));
//wait for the build to complete
$server2 -> WaitFor("COMPLETE", 300);

//pull the private IP out of the server object
$serverips = $server1 -> ips();
$privip1 = $serverips -> private[0] -> addr;
$serverips = $server2 -> ips();
$privip2 = $serverips -> private[0] -> addr;

//create a handle to Cloud Load Balancers
$cloudlb = $rsconnect -> LoadBalancerService('cloudLoadBalancers','DFW');

print("Creating load balancer $LBNAME...\n");
$loadbalancer = $cloudlb -> LoadBalancer();
print("$privip1   $privip2\n");
$loadbalancer -> AddNode($privip1, 80);
$loadbalancer -> AddNode($privip2, 80);
$loadbalancer -> AddVirtualIp('public');
$response = $loadbalancer -> Create(array('name' => $LBNAME,
                                          'protocol' => 'HTTP',
                                          'port' => 80));
$loadbalancer -> WaitFor("COMPLETE", 300);

?>