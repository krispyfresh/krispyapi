<?php

namespace OpenCloud;

// Author: Chris Parsons

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$DIRECTORY = $argv[1];  //This is the name of the Cloud Server you want to clone
$CONTAINER = $argv[2]; //Images are named $SERVERNAME.$IMAGEDESC

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

?>