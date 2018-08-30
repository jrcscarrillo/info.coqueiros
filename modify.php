<?php

/**
 * Example Web Connector application
 * 
 * This is a very simple application that allows someone to enter a customer 
 * name into a web form, and then adds the customer to QuickBooks.
 * 
 * @author Keith Palmer <keith@consolibyte.com>
 * 
 * @package QuickBooks
 * @subpackage Documentation
 */

/**
 * Require some configuration stuff
 */ 
require_once dirname(__FILE__) . '/config.php';

// Handle the form post
if (isset($_POST['submitted'])){

	// Save the record
	mysql_query("
            UPDATE my_customer_table SET
                name    = '".mysql_escape_string($_POST['name'])."',
                fname   = '".mysql_escape_string($_POST['fname'])."',
                lname   = '".mysql_escape_string($_POST['lname'])."'
            WHERE id = ".mysql_escape_string($_POST['id'])."
		
        ");
		
	
	// Queue up the customer modification 
	$Queue = new QuickBooks_WebConnector_Queue($dsn);
	$Queue->enqueue(QUICKBOOKS_MOD_CUSTOMER, $_POST['id']);
	
	die('Customer edited successfully.');
}



