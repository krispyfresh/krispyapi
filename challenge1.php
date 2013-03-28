<?php

namespace OpenCloud;

// Author: Chris Parsons
// This program creates 3 identical servers with the same base name.  If you set $SERVERNAME to "DB", this program will
// create DB1, DB2, and DB3 cloud servers.  OS and Memory values can be set well.

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

//set the requested OS and memory for the new Cloud Server
$OS = 'CentOS 6.3';
$MEMORY = 512;
$SERVERNAME = "db";

//include the rackspace library
require('rackspace.php');

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
$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', 'DFW');

//go through the list of flavors to find the right one
$flavorlist = $cloudservers -> FlavorList();
//$flavorlist -> Sort('id');
while($f = $flavorlist -> Next())
{
    if($f -> ram == $MEMORY)
        $RS_FLAVOR = $f;
}

//go through the list of images to find the right one
$imagelist = $cloudservers -> ImageList();
//$imagelist -> Sort('name');
while($i = $imagelist -> Next())
{
    if($i -> name == $OS)
        $RS_IMAGE = $i;
}

//set the cloud server properties
$serverinfo = array('image' => $RS_IMAGE,
                    'flavor' => $RS_FLAVOR);
$newserver = $cloudservers -> Server();

//create each cloud server
for($a = 1; $a <=3; $a++)
{
    $newserver -> name = $SERVERNAME . (string)$a;  //appends the incremental number to the end of $SERVERNAME
    $newserver -> Create($serverinfo);
    print("Building your $OS server named ".$newserver -> name."...\n");  
}

exit(0);