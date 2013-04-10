<?php

namespace OpenCloud;

// Author: Chris Parsons
// uploads a folder to cloud files
// takes two arguments, the folder and the cloud files container

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$DIRECTORY = $argv[1];  // upload files from this directory
$CONTAINER = $argv[2]; // upload files to this Cloud Files container
$DC = 'DFW'; // which DC you want to upload to

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

// create a handle to Cloud Files
$cloudfiles = $rsconnect -> ObjectStore('cloudFiles', $DC);

// create a handle to the directory
$dir = opendir($DIRECTORY);

if($dir) // does the local directory exist?  if it doesn't, don't waste your time!
{

    // check to see if the container exists
    $containerlist = $cloudfiles -> ContainerList();
    $containerfound = false;
    while($c = $containerlist -> Next())
    {
        // if the container already exists, we don't have to do anything but save a handle to it
        if($c -> name == $CONTAINER)
        {
            $containerfound = true;
            $container = $c;
            print("Adding files to existing container...\n");
        }
    }

    // if the container doesn't exist, create it, and save a handle to it
    if($containerfound == false)
    {
        $container = $cloudfiles -> Container();
        $container -> Create(array('name' => $CONTAINER));
        print("Created container $CONTAINER...\n");
    }

    // loop through each file in the directory and upload it to Cloud Files
    while($file = readdir($dir))
    {
        // if the file name is ".", "..", or is a directory, skip it
        if(!($file === ".") && !($file === "..") && !is_dir($file))
        {
            print("Uploading $file to Cloud Files container $CONTAINER... \n");
            $newobject = $container -> DataObject();
            $newobject -> Create(array('name' => $file,
                                       'content_type' => 'text/plain'), $DIRECTORY.'/'.$file);
        }
    }
}

if(!$dir)
{
    print("Cannot open directory.\n");
}

?>