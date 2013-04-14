<?php

namespace OpenCloud;

// Author: Chris Parsons
// this program takes a static webpage, uploads it to and serves it out of cloud files,
// CDN enables it, and creates a new CNAME record for it.

// include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

// include the rackspace library
require('rackspace.php');

// check that we have the right number of arguments
if(sizeof($argv) != 3)
{
    print("Incorrect number of arguments!\n");
    print("Usage: challenge8.php <local path to index file> <FQDN of new website>\n");
    exit();
}

$INDEX = $argv[1];  // the file you want to publish on Cloud Files, must be in the program folder
$FQDN = $argv[2]; // the FQDN of the CNAME record that will point to the CDN URL of the website
$CONTAINER = "Website_Files"; // the name of the Cloud Files container that will be created
$EMAIL = "chris.parsons@rackspace.com"; // email to use for the new DNS zone
$DC = 'DFW'; // DC to create all this stuff in

// read the ini file and store it in $ini as an array
// ini file is at .rackspace_cloud_credentials and contains:
// [authentication]
// username: $your_username
// apikey: $your_apikey
$ini = parse_ini_file('.rackspace_cloud_credentials', TRUE);

// get an auth token by passing the username and api key to the auth server
$auth = array('username' => $ini['authentication']['username'],
              'apiKey' => $ini['authentication']['apikey']);
$rsconnect = new Rackspace(RACKSPACE_US, $auth);

// create a handle to Cloud Files
$cloudfiles = $rsconnect -> ObjectStore('cloudFiles', $DC);

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
$domainname = substr($FQDN, strpos($FQDN, '.') + 1); 

$domain = $dns -> Domain(array('name' => $domainname,
                               'emailAddress' => $EMAIL,
                               'ttl' => 28800));

$record = $domain -> Record(array('type' => 'CNAME',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $indexfile -> CDNUrl()));
$domain -> AddRecord($record);
$response = $domain -> Create();
$response -> WaitFor("COMPLETED",10);
print("Created DNS zone for $domainname with CNAME record $FQDN...\n");

?>