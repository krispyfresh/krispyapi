<?php

namespace OpenCloud;

// Author: Chris Parsons

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$SERVERNAME = 'web'; // base name of the servers that will be created, a number will be appended to this
$IMAGE_ID = 'c195ef3b-9195-4474-b6f7-16e5bd86acd0'; // CentOS 6.3
$FLAVOR_ID = 2; // 512 MB slice
$CBS_SIZE = 100; // size of CBS volumes in GB - number must be between 100 and 1024
$LBNAME = 'KrispyLB'; // name of LB to create
$DC = 'DFW'; // the DC to create the servers in

// include the rackspace library
require('rackspace.php');

// read the ini file and store it in $ini as an array
// ini file is at .rackspace_cloud_credentials and contains:
// [authentication]
// username: $your_username
// apikey: $your_apikey
$ini = parse_ini_file(".rackspace_cloud_credentials", TRUE);

// get an auth token by passing the username and api key to the auth server
$auth = array('username' => $ini['authentication']['username'],
              'apiKey' => $ini['authentication']['apikey']);
$rsconnect = new Rackspace(RACKSPACE_US, $auth);

$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', $DC);

$cloudnetwork = $cloudservers -> Network();
$cloudnetwork -> Create(array('label' => 'KrispyNet2',
                              'cidr' => '10.10.10.0/24'));

print("Creating servers...\n");
for($a = 1; $a <= 3; $a++)
{
    $servers[$a] = $cloudservers -> Server();
    //$servers[$a] -> name = $SERVERNAME.(string)$a;
    $servers[$a] -> Create(array('name' => $SERVERNAME.(string)$a,
                                 'image' => $cloudservers -> Image($IMAGE_ID),
                                 'flavor' => $cloudservers -> Flavor($FLAVOR_ID),
                                 'networks' => array($cloudservers -> Network(RAX_PUBLIC),
                                                     $cloudservers -> Network(RAX_PRIVATE),
                                                     $cloudnetwork)));
    $rootpassword[$a] = $servers[$a] -> adminPass;
}

while($servers[1] -> status != 'ACTIVE' && $servers[2] -> status != 'ACTIVE' && $servers[3] -> status != 'ACTIVE')
{
    for($a = 1; $a <= 3; $a++)
        $servers[$a] = $cloudservers -> Server($servers[$a] -> id);
    sleep(15);
}
print("Server build complete!\n");

// create the CBS volumes
print("Creating and attaching a $CBS_SIZE GB CBS volume to each server...\n");

$cbs = $rsconnect -> VolumeService('cloudBlockStorage', $DC);

for($a = 1; $a <= 3; $a++)
{
    $volume = $cbs -> Volume();
    $volume -> Create(array('display_name' => $SERVERNAME.(string)$a."_CBS",
                            'size' => $CBS_SIZE,
                            'volume_type' => $cbs -> VolumeType(1))); // volume type 1 = SATA, while 2 = SSD
    $servers[$a] -> AttachVolume($volume);
}

print("Cloud Block Storage is attached!\n");

// create the load balancer
print("Creating load balancer $LBNAME...\n");
$cloudlb = $rsconnect -> LoadBalancerService('cloudLoadBalancers',$DC);

$loadbalancer = $cloudlb -> LoadBalancer();
for($a = 1; $a <= 3; $a++)
{
    $serverips = $servers[$a] -> ips();
    $privateip = $serverips -> private[0] -> addr;
    $loadbalancer -> AddNode($servers[$a] -> $privateip, 443);
}
$loadbalancer -> Create(array('name' => $LBNAME,
                                          'protocol' => 'HTTPS',
                                          'port' => 443));

// wait for the LB to come online
while($loadbalancer -> status != 'ACTIVE')
{
    $loadbalancer = $cloudlb -> LoadBalancer($loadbalancer -> id);
    sleep(5);
}

$loadbalancer -> SSLTermination(array('certificate' => xyz,
                                      'enabled' => true,
                                      'privatekey' => abc));



?>