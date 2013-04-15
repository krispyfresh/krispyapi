<?php

namespace OpenCloud;

// Author: Chris Parsons
// creates an instance, a database, and a user for that database

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$INSTANCENAME = 'TEST'; // name of the instance to create
$FLAVOR = 1; // 1 = 512 MB, 2 = 1 GB, 3 = 2 GB, 3 = 4 GB, and so on, up to 16 GB
$SIZE = 1; // size of the disk in GB, from 1 to 250
$DBNAME = 'KripsyDB'; // name of the database to create
$USERNAME = 'krispy'; // username that will be created in the new database
$USERPASS = 'Rackspace1'; // password for the new user account
$DC = 'DFW'; // which DC to create the instance and DB in

// include the rackspace library
require('rackspace.php');

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

// get a handle to cloud databases
$clouddb = $rsconnect -> DbService('cloudDatabases', $DC);

print("Creating new instance $INSTANCENAME...\n");
// set the parameters for the new instance
$instance = $clouddb -> Instance();
$instance -> name = $INSTANCENAME;
$instance -> flavor = $clouddb -> Flavor($FLAVOR);
$instance -> volume -> size = $SIZE;
$instance -> Create();
// wait for the instance to build
while($instance -> status != 'ACTIVE')
{
   $instance = $clouddb -> Instance($instance -> id);
   sleep(15);
}
print("Instance creation complete\n");

print("Creating new database $DBNAME...\n");
$database = $instance -> Database();
$database -> Create(array('name' => $DBNAME));

print("Creating user account $USERNAME...\n");
$username = $instance -> User();
$username -> AddDatabase($DBNAME);
$username -> Create(array('name' => $USERNAME,
                          'password' => $USERPASS));

?>