<?php

namespace OpenCloud;

// Author: Chris Parsons
// This program creates a clone of $SERVERNAME and names it $SERVERNAME_CLONE.
// It will create an image file named $SERVERNAME.$IMAGEDESC

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

// check that we have the right number of arguments
if(sizeof($argv) != 2)
{
    print("Incorrect number of arguments!\n");
    print("Usage: challenge2.php <servername_to_clone>\n");
    exit();
}

// check that the given server name contains only numbers and letters
if(!ctype_alnum($argv[1]))
{
    print("Cannot create cloud server whose name has non-alphanumeric characters.\n");
    exit();
}

$SERVERNAME = $argv[1];  // this is the name of the Cloud Server you want to clone
$IMAGEDESC = '_IMAGE'; // images are named $SERVERNAME.$IMAGEDESC
$DC = 'DFW'; // the DC we will work in

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

// cycle through your server and look for $SERVERNAME
// a handle to the server is stored as $server
$serverlist = $cloudservers -> ServerList();
$serverfound = false;

// cycle through the list of servers and get a handle to the first one to match $SERVERNAME
while($s = $serverlist -> Next())
{
    if($s -> name == $SERVERNAME)
    {
        $server = $s;
        $serverfound = true;
    }
}

if($serverfound)
{
    // create the snapshot
    $server -> CreateImage($SERVERNAME.$IMAGEDESC);
    print("Creating an image of $SERVERNAME called ".$SERVERNAME.$IMAGEDESC."...\n");
    
    // wait for the image to build
    do
    {
        $serverimage = $cloudservers -> ImageList(TRUE, array('name' => $SERVERNAME.$IMAGEDESC)) -> Next(); // get a handle to the image we just made
        sleep(15);
    }
    while($serverimage -> status != 'ACTIVE');
    print("Image creation completed!\n");

    // create a new server from the image we created, using the attributes of the original server
    $newserver = $cloudservers -> Server();
    $newserver -> Create(array('name' => $server -> name.'_CLONE',
                               'image' => $server -> image,
                               'flavor' => $server -> flavor));
    print("Creating server ".$server -> name."_CLONE...\n");
    
    // wait for the server to build
    while($newserver -> status != 'ACTIVE')
    {
        $newserver = $cloudservers -> Server($newserver -> id);  // refresh the server info so we can check the current status
        sleep(15);
    }
    print("Server creation completed!\n");
}

if(!$serverfound)
    print("Server $SERVERNAME does not exist\n");

?>