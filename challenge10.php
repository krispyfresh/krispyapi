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
$ERRPAGECONTENT = '<html><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8"><title>Service Unavailable</title><style type="text/css">body, p, h1 {font-family: Verdana, Arial, Helvetica, sans-serif;}h2 {font-family: Arial, Helvetica, sans-serif;color: #b10b29;}</style></head><body><h2>Service Unavailable</h2><p>The service is temporarily unavailable. Please try again later.  Sorry, bro!</p></body></html>';
$FQDN = "test.krispy.com"; //FQDN to point to the LB

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
$loadbalancer -> AddNode("1.1.1.1", 80);
$loadbalancer -> AddNode("2.2.2.2", 80);
//$loadbalancer -> AddNode($privip1, 80);
//$loadbalancer -> AddNode($privip2, 80);
$loadbalancer -> AddVirtualIp('public');

$response = $loadbalancer -> Create(array('name' => $LBNAME,
                                          'protocol' => 'HTTP',
                                          'port' => 80));
$loadbalancer -> WaitFor("COMPLETE", 60);

$errorpage = $loadbalancer -> ErrorPage();
$errorpage -> Create(array('content' => ERRPAGECONTENT));

$errorpage -> WaitFor("COMPLETE", 10);

$connectionthrottle = $loadbalancer -> ConnectionThrottle();

$connectionthrottle -> Create(array('minConnections' => 0,
                                      'maxConnections' => 10,
                                'maxConnectionRate' => 100,
                                'rateInterval' => 10));

$lbip = $loadbalancer -> virtualIps[0] -> address;

$lbip = "1.2.3.4";
//create a handle to cloud DNS
$dns = $rsconnect -> DNS();

//create a list of all domains on the account
$domainlist = $dns -> DomainList();

//strip the prefix from the FQDN to see if "root" domain exists
//if $FQDN is "test.krispy.com", $domainname will be "krispy.com"
$domainname = substr($FQDN,strpos($FQDN,'.') + 1); 
$newdomainname = true;
//see if "root" domain exists
while($domain = $domainlist -> Next())
{
    //if "root" domain found, create a new A record within that domain
    if($domain -> Name() == $domainname)
    {
        print("Adding A record $FQDN [$lbip] to existing domain $domainname...\n");
        $record = $domain -> Record();
        $response = $record -> Create(array('type' => 'A',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $Lbip));
        $response -> Waitfor("COMPLETE", 10);
        
        if($response -> Status() == 'ERROR')
        {
            print("Error creating record\n");
            print($response->error->code."\n".$response->error->message."\n".$response->error->details."\n");
        }
        $newdomainname = false;
    }
}

if($newdomainname == true)
{
    print("Creating new domain $FQDN and adding A record for $lbip...\n");
    //add a new domain $FQDN
    $domain = $dns -> Domain(array('name' => $FQDN,
                                   'emailAddress' => "admin@".$domainname,
                                   'ttl' => 28800));
    //add a new A record to the new domain
    $record = $domain -> Record(array('type' => 'A',
                                      'name' => $FQDN,
                                      'ttl' => 28800,
                                      'data' => $lbip));
    $domain -> AddRecord($record);
    $response = $domain -> Create();
    $response -> WaitFor("COMPLETE",5);
}

//create a handle to cloud files
$cloudfiles = $rsconnect -> ObjectStore('cloudFiles', 'DFW');

//we have to write the error page to a file before we can upload it to Cloud Files
$file = fopen('error.htm', 'w');
fwrite($file, $ERRPAGECONTENT);

print("Creating container Error Page...\n");
$container = $cloudfiles -> Container();

$container = $cloudfiles -> Container();
$container -> Create(array('name' => 'Error Page'));
$newobject = $container -> DataObject();
$newobject -> Create(array('name' => 'error.htm',
                                   'content_type' => 'text/plain'), 'error.htm');

unlink('error.htm');




?>