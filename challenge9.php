<?php

namespace OpenCloud;

// Author: Chris Parsons

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

//include the rackspace library
require('rackspace.php');

$FQDN = $argv[1];
$OS = $argv[2];
$MEMORY = $argv[3];
$EMAIL = "chris.parsons@rackspace.com"; //email to use for the new DNS zone

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

//go through the list of flavors to find the right one
$flavorlist = $cloudservers -> FlavorList();
while($f = $flavorlist -> Next())
{
    if($f -> ram == $MEMORY)
        $flavor = $f;
}

//go through the list of images to find the right one
$imagelist = $cloudservers -> ImageList();
while($i = $imagelist -> Next())
{
    if($i -> name == $OS)
        $image = $i;
}

$server = $cloudservers -> Server();
$server -> Create(array('name' => $FQDN,
                                     'image' => $image,
                                     'flavor' => $flavor));
print("Creating Cloud Server $FQDN...\n");
$server -> WaitFor("COMPLETE",300);

//create a handle to Cloud DNS
$dns = $rsconnect -> DNS();

//get the actual domain name
//if new DNS record is for test.krispy.com, this will return krispy.com
$domainname = substr($FQDN, strpos($FQDN, '.') + 1);

$domain = $dns -> Domain(array('name' => $domainname,
                               'emailAddress' => $EMAIL,
                               'ttl' => 28800));

$record = $domain -> Record(array('type' => 'A',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $server -> ip(4)));
$domain -> AddRecord($record);
$response = $domain -> Create();
print("Creating A record for $FQDN in DNS zone $domainname...\n");
$response -> WaitFor("COMPLETED",10);

?>