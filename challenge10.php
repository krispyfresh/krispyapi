<?php

namespace OpenCloud;

// Author: Chris Parsons
// this application does the following:
// creates 2 servers, then uploads an ssh key and installs it in /root/.ssh/authorized_keys
// creates a new load balancer, putting the 2 new servers behind it
// sets up a health monitor and custom error page on the LB
// creates a DNS record for the load balancer
// writes the custom error pages to cloud files as a backup
//
// all values are set through constants - there are no command line arugments!

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

// include the rackspace library
require('rackspace.php');

$SERVERPREFIX = 'web'; // will create two servers, $SERVERPREFIX1 and $SERVERPREFIX2
$IMAGE_ID = 'c195ef3b-9195-4474-b6f7-16e5bd86acd0'; // CentOS 6.3
$FLAVOR_ID = 2; // 512 MB slice
$LBNAME = "KrispyLB"; // name of the LB to sit in front of the new servers
$ERRPAGECONTENT = '<html><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8"><title>Service Unavailable</title><style type="text/css">body, p, h1 {font-family: Verdana, Arial, Helvetica, sans-serif;}h2 {font-family: Arial, Helvetica, sans-serif;color: #b10b29;}</style></head><body><h2>Service Unavailable</h2><p>The service is temporarily unavailable. Please try again later.  Sorry, bro!</p></body></html>';
$FQDN = "test.krispy.com"; // FQDN to point to the LB
$LOCAL_SSH_KEY = '/Users/chris.parsons/.ssh/id_rsa.pub'; // local path to your public SSH key, full path only!!
$DC = 'DFW'; // DC to create device in

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

// create a handle to Cloud Servers
$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', $DC);

// create both cloud servers
print("Creating "."$SERVERPREFIX"."1 and $SERVERPREFIX"."2...\n");
$server1 = $cloudservers -> Server();
$server1 -> AddFile("/root/.ssh/authorized_keys",file_get_contents($LOCAL_SSH_KEY)); // upload your SSH key to the server
$server1 -> Create(array('name' => $SERVERPREFIX."1",
                         'image' => $cloudservers -> Image($IMAGE_ID),
                         'flavor' => $cloudservers -> Flavor($FLAVOR_ID)));

$server2 = $cloudservers -> Server();
$server2 -> AddFile("/root/.ssh/authorized_keys",file_get_contents($LOCAL_SSH_KEY)); // upload your SSH key to the server
$server2 -> Create(array('name' => $SERVERPREFIX."2",
                         'image' => $cloudservers -> Image($IMAGE_ID),
                         'flavor' => $cloudservers -> Flavor($FLAVOR_ID)));

// wait for the build to complete
while($server1 -> status != 'ACTIVE' && $server2 -> status != 'ACTIVE')
{
    $server1 = $cloudservers -> Server($server1 -> id);
    $server2 = $cloudservers -> Server($server2 -> id);
    sleep(15);
}


// pull the private IP out of the server object
$serverips = $server1 -> ips();
$privip1 = $serverips -> private[0] -> addr;
$serverips = $server2 -> ips();
$privip2 = $serverips -> private[0] -> addr;

// create a handle to Cloud Load Balancers
$cloudlb = $rsconnect -> LoadBalancerService('cloudLoadBalancers',$DC);

print("Creating load balancer $LBNAME with nodes $privip1 and $privip2...\n");
$loadbalancer = $cloudlb -> LoadBalancer();

// add node IPs to the LB
$loadbalancer -> AddNode($privip1, 80);
$loadbalancer -> AddNode($privip2, 80);
$loadbalancer -> AddVirtualIp('public');

// create the LB with the health check (this is the only time to set up the health check with php-opencloud at this time)
$loadbalancer -> Create(array('name' => $LBNAME,
                                          'protocol' => 'HTTP',
                                          'port' => 80,
                                          'healthMonitor' => array('type' => 'CONNECT',
                                                                   'delay' => 10,
                                                                   'timeout' => 30,
                                                                   'attemptsBeforeDeactivation' => 3)));

// wait for the LB to come online
while($loadbalancer -> status != 'ACTIVE')
{
    $loadbalancer = $cloudlb -> LoadBalancer($loadbalancer -> id);
    sleep(5);
}

// set up the LB error page
$errorpage = $loadbalancer -> ErrorPage();
$errorpage -> Create(array('content' => $ERRPAGECONTENT));

// get the public IP of the load balancer so we can publish a new DNS record
$lbip = $loadbalancer -> virtualIps[0] -> address;

// create a handle to cloud DNS
$dns = $rsconnect -> DNS();

// create a list of all domains on the account
$domainlist = $dns -> DomainList();

// strip the prefix from the FQDN to see if "root" domain exists
// if $FQDN is "test.krispy.com", $domainname will be "krispy.com"
$domainname = substr($FQDN,strpos($FQDN,'.') + 1); 
$newdomainname = true;
// see if "root" domain exists
while($domain = $domainlist -> Next())
{
    // if "root" domain found, create a new A record within that domain
    if($domain -> Name() == $domainname)
    {
        print("Adding A record $FQDN [$lbip] to existing domain $domainname...\n");
        $record = $domain -> Record();
        $response = $record -> Create(array('type' => 'A',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $Lbip));
        $response -> Waitfor("COMPLETE", 10);
        $newdomainname = false;
    }
}
// if the domain name doesn't exist, create it, then add the record to the new domain
if($newdomainname == true)
{
    print("Creating new domain $FQDN and adding A record for $lbip...\n");
    // add a new domain $FQDN
    $domain = $dns -> Domain(array('name' => $domainname,
                                   'emailAddress' => "admin@".$domainname,
                                   'ttl' => 28800));
    // add a new A record to the new domain
    $record = $domain -> Record(array('type' => 'A',
                                      'name' => $FQDN,
                                      'ttl' => 28800,
                                      'data' => $lbip));
    $domain -> AddRecord($record);
    $response = $domain -> Create();
}

// create a handle to cloud files
$cloudfiles = $rsconnect -> ObjectStore('cloudFiles', 'DFW');

// we have to write the error page to a file before we can upload it to Cloud Files
$file = fopen('error.htm', 'w');
fwrite($file, $ERRPAGECONTENT);

print("Creating container Error Page...\n");
$container = $cloudfiles -> Container();
$container -> Create(array('name' => 'Error Page'));
$newobject = $container -> DataObject();
$newobject -> Create(array('name' => 'error.htm',
                                   'content_type' => 'text/plain'), 'error.htm');
// delete the temp file we just created
unlink('error.htm');

?>