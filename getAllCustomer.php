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
if (isset($_POST['submitted']))
{
	// Save the record
	mysql_query("
		SELECT * from
			my_customer_table
		");
		
	// Queue up the customer add 
	$Queue = new QuickBooks_WebConnector_Queue($dsn);
	$customers = $Queue->enqueue(QUICKBOOKS_OBJECT_CUSTOMER);
        
        echo '<pre>';
        print_r($customers);
	echo '</pre>';
        
        
	die('Great, queued up a customer!');
}
