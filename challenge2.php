<?php

namespace OpenCloud;

// Author: Chris Parsons
// This program creates a clone of $SERVERNAME and names it $SERVERNAME_CLONE.
// It will create an image file named $SERVERNAME.$IMAGEDESC

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$SERVERNAME = "CentOS";  //This is the name of the Cloud Server you want to clone
$IMAGEDESC = "_IMAGE"; //Images are named $SERVERNAME.$IMAGEDESC

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

//create a handle to Cloud Servers
$cloudservers = $rsconnect -> Compute('cloudServersOpenStack', 'DFW');

//cycle through your server and look for $SERVERNAME
//a handle to the server is stored as $server
$serverlist = $cloudservers -> ServerList();
while($s = $serverlist -> Next())
    if($s -> name == $SERVERNAME)
        $server = $s;
        
//create image $SERVERNAME.$IMAGEDESC, and wait for it to finish
$server -> CreateImage($SERVERNAME.$IMAGEDESC);
print("Creating an image of $SERVERNAME called ".$SERVERNAME.$IMAGEDESC."...\n");
$server -> WaitFor("COMPLETE", 300);  //COMPLETE is not a valid status, so this will wait for 5 minutes
print("DONE!\n");

//cycle through your images and look for the image we just created
//a handle to the server is stored as $image
$imagelist = $cloudservers -> ImageList();
while($i = $imagelist -> Next())
{
    if($i -> name == $server -> name.$IMAGEDESC)
        $image = $i;
}

//create a new server from the image we created
$newserver = $cloudservers -> Server();
$newserver -> Create(array('name' => $server -> name."_CLONE",
                           'image' => $image,
                           'flavor' => $server -> flavor));
print("Creating server ".$server -> name."_CLONE\n");
$newserver -> WaitFor("ACTIVE");
print("DONE!\n");

?>