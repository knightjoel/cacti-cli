<?php
/*
  $jwk$

  add_threshold_contact.php

  In Thold 0.3.7 (the version needed for Cacti 0.8.7), threshold alerts
  are sent to a list of addresses which are configured on a per-threshold
  basis; the old behavior of specifying a global list of recipients is
  gone. This script will populate the list of contacts for each configured
  threshold.

  Usage:
  1. Login to Cacti, User Management and add an email address for the admin
     user (or whichever user(s) you want to use as contacts for threshold
     alerts.
  2. Place this script in your cacti/cli/ directory.
  3. Run the script like this:
      php add_threshold_contact.php <user1> [<user2> ...]


  Joel Knight
  <knight.joel@gmail.com>
  2007.10.27 
*/


/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include_once($config["base_path"] . "/lib/auth.php");

/* argv[0] is the name of the script */
array_shift($_SERVER["argv"]);

foreach ($_SERVER["argv"] as $username) {
	$sql = "SELECT ua.id, ua.username, contacts.data," .
		"contacts.id AS contact_id " .
		"FROM plugin_thold_contacts AS contacts " .
		"INNER JOIN user_auth AS ua ON ua.id = contacts.user_id  " .
		"WHERE ua.username = '$username'";
	$user = db_fetch_row($sql);
	if (count($user) == 0) {
		printf("Error: User %s does not have an email address.\n", $username);
		continue;
	}
	printf("Adding %s (%s) to thresholds:\n", $username, $user["data"]);

	$sql = "SELECT thold.id AS thold_id, dtd.name_cache, dtr.data_source_name " .
		"FROM thold_data AS thold ".
		"INNER JOIN data_template_data AS dtd ON thold.rra_id = dtd.local_data_id " .
		"INNER JOIN data_template_rrd AS dtr ON thold.data_id = dtr.id " .
		"ORDER BY dtd.name_cache ASC"; 
	$thresholds = db_fetch_assoc($sql);
	foreach ($thresholds as $th) {
		printf("\t%s [%s]\n", $th["name_cache"], $th["data_source_name"]);
		$sql = "DELETE FROM plugin_thold_threshold_contact " .
			"WHERE thold_id = $th[thold_id] " .
			"AND contact_id = $user[contact_id]";
		db_execute($sql);
		$sql = "INSERT INTO plugin_thold_threshold_contact " .
			"VALUES ($th[thold_id], $user[contact_id])";
		db_execute($sql);
	}
}

?>
