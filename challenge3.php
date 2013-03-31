<?php

namespace OpenCloud;

// Author: Chris Parsons

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$DIRECTORY = $argv[1];  //Upload files from this directory
$CONTAINER = $argv[2]; //Upload files to this Cloud Files container

//include the rackspace library
require('rackspace.php');

function UploadProgress($len)
{
    printf("[uploading %d bytes]", $len);
}


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

//create a handle to Cloud Files
$cloudfiles = $rsconnect -> ObjectStore('cloudFiles', 'DFW');
$rsconnect->SetUploadProgressCallback('OpenCloud\UploadProgress');

//check to see if the container exists
$containerlist = $cloudfiles -> ContainerList();
$containerfound = false;
while($c = $containerlist -> Next())
{
    //if the container already exists, we don't have to do anything but save a handle to it
    if($c -> name == $CONTAINER)
    {
        $containerfound = true;
        $container = $c;
        print("Found container\n");
    }
}

//if the container doesn't exist, create it, and save a handle to it
if($containerfound == false)
{
    $container = $cloudfiles -> Container();
    $container -> Create(array('name' => $CONTAINER));
    print("Created container $CONTAINER\n");
}

//create a handle to the directory
$dir = opendir($DIRECTORY);
while($file = readdir($dir))
{
    if(!($file === ".") && !($file === ".."))
    {
        print("Uploading $file to Cloud Files container $CONTAINER ");
        $newobject = $container -> DataObject();
        //sleep(2);
        $newobject -> Create(array('name' => $file,
                                   'content_type' => 'text/plain'),__FILE__);
    }
}

?>