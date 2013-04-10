<?php

namespace OpenCloud;

// Author: Chris Parsons
// This program creates 3 identical servers with the same base name.  If you set $SERVERNAME to "DB", this program will
// create DB1, DB2, and DB3 cloud servers.  Image and flavor can be changed but will be the same for all 3 servers.

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

// set the requested OS and memory for the new Cloud Server
$IMAGE_ID = 'c195ef3b-9195-4474-b6f7-16e5bd86acd0'; // CentOS 6.3
$FLAVOR_ID = 2; // 512 MB slice
$SERVERNAME = 'web'; // if $SERVERNAME is 'web', this program will create WEB1, WEB2, and WEB3
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

// create a handle to Cloud Servers
$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', $DC);

// set the cloud server properties
$serverinfo = array('image' => $cloudservers -> Image($IMAGE_ID),
                    'flavor' => $cloudservers -> Flavor($FLAVOR_ID));

// create each cloud server
for($a = 1; $a <=3; $a++)
{
    $newserver = $cloudservers -> Server(); // initialize a new Server object
    $newserver -> name = $SERVERNAME.(string)$a; // appends the incremental number to the end of $SERVERNAME
    $newserver -> Create($serverinfo); // create the new server
    $rootpassword = $newserver -> adminPass; // root password is only returned when the server is created, so save it
    print("Building your new server ".$newserver -> name."...\n");
    while($newserver -> status != 'ACTIVE')
    {
        $newserver = $cloudservers -> Server($newserver -> id);  // refresh the server info so we can check the current status
        sleep(15);
    }
    print("Created server $SERVERNAME".$a." with IP ".$newserver -> ip(). " and root password ".$rootpassword."\n");  
}

?>