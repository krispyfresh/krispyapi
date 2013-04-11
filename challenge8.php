<?php

namespace OpenCloud;

// Author: Chris Parsons

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

// include the rackspace library
require('rackspace.php');

$INDEX = $argv[1];  // the file you want to publish on Cloud Files, must be in the program folder
$CNAME = $argv[2]; // the CNAME pointing to the CDN URL
$CONTAINER = "Website_Files"; // the name of the Cloud Files container that will be created
$EMAIL = "chris.parsons@rackspace.com"; // email to use for the new DNS zone

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
$cloudfiles = $rsconnect -> ObjectStore('cloudFiles', 'DFW');

// create the container
$container = $cloudfiles -> Container();
$container -> Create(array('name' => $CONTAINER));
print("Created container $CONTAINER...\n");

// publish the container to CDN
$container -> PublishToCDN();
print("Published $CONTAINER to CDN...\n");

$container -> CreateStaticSite($INDEX);
print("Created static site with index page $INDEX...\n");

$indexfile = $container -> DataObject();
$indexfile -> Create(array('name' => $INDEX,
                           'content_type' => 'text/plain'), $INDEX);
print("Uploaded $INDEX to Cloud Files container $CONTAINER...\n");
 
// create a handle to Cloud DNS
$dns = $rsconnect -> DNS();

// get the actual domain name
// if desired CNAME is test.krispy.com, this will return krispy.com
$domainname = substr($CNAME, strpos($CNAME, '.') + 1); 

$domain = $dns -> Domain(array('name' => $domainname,
                               'emailAddress' => $EMAIL,
                               'ttl' => 28800));

$record = $domain -> Record(array('type' => 'CNAME',
                                  'name' => $CNAME,
                                  'ttl' => 28800,
                                  'data' => $indexfile -> CDNUrl()));
$domain -> AddRecord($record);
$response = $domain -> Create();
$response -> WaitFor("COMPLETED",10);
print("Created DNS zone for $domainname with CNAME record $CNAME...\n");

?>