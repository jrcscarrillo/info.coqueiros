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
 * Generate a qbXML response to add a particular customer to QuickBooks
 */
function _quickbooks_customer_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale){
	// Grab the data from our MySQL database
	$arr = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = " . (int) $ID));
	
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
                        <CustomerAddRq requestID="' . $requestID . '">
                            <CustomerAdd>
                                <Name>' . $arr['name'] . '</Name>
                                <CompanyName>' . $arr['name'] . '</CompanyName>
                                <Salutation >'.$arr['salutation'].'</Salutation>
                                <FirstName>' . $arr['fname'] . '</FirstName>
                                <LastName>' . $arr['lname'] . '</LastName>
                                <BillAddress>
                                    <Addr1 >'.$arr['address'] .'</Addr1> 
                                    <City >'.$arr['city'].'</City>
                                    <State >'.$arr['state'].'</State>
                                    <PostalCode >'.$arr['zipcode'].'</PostalCode>
                                    <Country >'.$arr['country'].'</Country>
                                </BillAddress>
                            </CustomerAdd>
                        </CustomerAddRq>
                    </QBXMLMsgsRq>
		</QBXML>';
	
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_customer_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents){	
	mysql_query("
		UPDATE 
			my_customer_table 
		SET 
			quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "', 
			quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
		WHERE 
			id = " . (int) $ID);
}

/**
 * Catch and handle an error from QuickBooks
 */
function _quickbooks_error_catchall($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg){
	mysql_query("
		UPDATE 
			my_customer_table 
		SET 
			quickbooks_errnum = '" . mysql_real_escape_string($errnum) . "', 
			quickbooks_errmsg = '" . mysql_real_escape_string($errmsg) . "'
		WHERE 
			id = " . (int) $ID);
}

function _quickbooks_customer_mod_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale){
    
        $arr = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = " . (int) $ID));
	
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
                    <QBXMLMsgsRq onError="stopOnError">
                        <CustomerModRq requestID="' . $requestID . '">
                            <CustomerMod>           
                                <ListID >'. $arr['quickbooks_listid'] .'</ListID>
                                <EditSequence >'. $arr['quickbooks_editsequence'] .'</EditSequence>
                                <Name>' . $arr['name'] . '</Name>
                                <CompanyName>' . $arr['name'] . '</CompanyName>
                                <Salutation >'.$arr['salutation'].'</Salutation>
                                <FirstName>' . $arr['fname'] . '</FirstName>
                                <LastName>' . $arr['lname'] . '</LastName>   
                                <BillAddress>
                                    <Addr1 >'.$arr['address'] .'</Addr1> 
                                    <City >'.$arr['city'].'</City>
                                    <State >'.$arr['state'].'</State>
                                    <PostalCode >'.$arr['zipcode'].'</PostalCode>
                                    <Country >'.$arr['country'].'</Country>
                                </BillAddress>
                            </CustomerMod>                                        
                        </CustomerModRq>
                    </QBXMLMsgsRq>
		</QBXML>';
	
	return $xml;
}

function _quickbooks_customer_mod_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents){
    mysql_query("
        UPDATE 
                my_customer_table 
        SET 
                quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "', 
                quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
        WHERE 
                id = " . (int) $ID);
}

function _quickbooks_item_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale){
    
    $arr = mysql_fetch_assoc(mysql_query("SELECT * FROM items WHERE id = " . (int) $ID));
	
    $xml = '<?xml version="1.0" encoding="utf-8"?>
            <?qbxml version="2.0"?>
            <QBXML>
                <QBXMLMsgsRq onError="stopOnError">
                    <ItemServiceAddRq>
                        <ItemServiceAdd>
                            <Name>' . $arr['name'] . '</Name> 
                            <IsActive >TRUE</IsActive>
                            <SalesOrPurchase> 
                                <Desc>' . $arr['description'] . '</Desc>
                                <Price>' . (int)$arr['amount']. '</Price> 
                            </SalesOrPurchase>
                        </ItemServiceAdd>
                    </ItemServiceAddRq>
                </QBXMLMsgsRq>
            </QBXML>';

    return $xml;
}

function _quickbooks_item_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents){	
	mysql_query("
		UPDATE 
			items 
		SET 
			quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "', 
			quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
		WHERE 
			id = " . (int) $ID);
}

function _quickbooks_sales_order_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale){
    
    $arr                = mysql_fetch_assoc(mysql_query("SELECT * FROM customer_sales_order WHERE id = ".(int)$ID));
            $sales_items        = mysql_query("SELECT * FROM sales_order_items WHERE sales_order_id = ".(int)$ID);
            $sales_items_arr    = array();
            
            while($row = mysql_fetch_assoc($sales_items)) {
                $sales_items_arr[$row['item_id']]    = $row;
                $sales_item_ids[]                    = $row['item_id'];
            }
           
            $customer   = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = ".(int)$arr['customer_id']));
            $items      = mysql_query("SELECT * FROM items WHERE id IN (".implode(',',$sales_item_ids).")");
            
            $items_details = array();
            
            while($row = mysql_fetch_assoc($items)) {
                $items_details[$row['id']]    = $row;                
            } 
                  
            if($arr['shipping_address']){
                $shipping_address['address']    = $customer['address'];
                $shipping_address['city']       = $customer['city'];
                $shipping_address['state']      = $customer['state'];
                $shipping_address['country']    = $customer['country'];
                $shipping_address['zipcode']    = $customer['zipcode'];
            }else{
                $shipping_address['address']    = $arr['address'];
                $shipping_address['city']       = $arr['city'];
                $shipping_address['state']      = $arr['state'];
                $shipping_address['country']    = $arr['country'];
                $shipping_address['zipcode']    = $arr['zipcode'];
            }

            $item_xml = "";
            
            foreach($sales_items_arr as $item_id => $item_detail){
                $item_xml .= '<SalesOrderLineAdd>
                    <ItemRef>
                        <ListID >'.$items_details[$item_id]['quickbooks_listid'].'</ListID>
                        <FullName >'.$items_details[$item_id]['fullname'].'</FullName>
                    </ItemRef>
                    <Desc >'.$items_details[$item_id]['description'].'</Desc>
                    <Quantity >'.$sales_items_arr[$item_id]['quantity'].'</Quantity>                    
                    <Rate >'.$items_details[$item_id]['sales_price'].'</Rate>
                </SalesOrderLineAdd>';
            }
            
            $xml = '<?xml version="1.0" encoding="utf-8"?>
<?qbxml version="11.0"?>
<QBXML>
    <QBXMLMsgsRq onError="continueOnError">
        <SalesOrderAddRq>
            <SalesOrderAdd>
                <CustomerRef>
                    <ListID >'.$customer['quickbooks_listid'].'</ListID>
                    <FullName >'.$customer['fullname'].'</FullName> 
                </CustomerRef>  
                <TxnDate >'.$arr['date'].'</TxnDate>
                <BillAddress>
                    <Addr1 >'.$customer['address'].'</Addr1>                    
                    <City >'.$customer['city'].'</City>                     
                </BillAddress>
                <ShipAddress>
                    <Addr1 >'.$customer['address'].'</Addr1>                    
                    <City >'.$customer['city'].'</City>                                                          
                </ShipAddress>    
                '.$item_xml.'
            </SalesOrderAdd>
        </SalesOrderAddRq>
    </QBXMLMsgsRq>
</QBXML>';

    return $xml;
}

function _quickbooks_sales_order_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents){	
	mysql_query("
		UPDATE 
			customer_sales_order 
		SET 
			quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "',
                        reference_number = '". mysql_real_escape_string($idents['RefNumber']) ."',
			quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
		WHERE 
			id = " . (int) $ID);
}

function _quickbooks_invoice_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale){
    
    $arr                = mysql_fetch_assoc(mysql_query("SELECT * FROM customer_sales_order WHERE id = ".(int)$ID));
    $sales_items        = mysql_query("SELECT * FROM sales_order_items WHERE sales_order_id = ".(int)$ID);
    $sales_items_arr    = array();

    while($row = mysql_fetch_assoc($sales_items)) {
        $sales_items_arr[$row['item_id']]    = $row;
        $sales_item_ids[]                    = $row['item_id'];
    }

    $customer   = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = ".(int)$arr['customer_id']));
    $items      = mysql_query("SELECT * FROM items WHERE id IN (".implode(',',$sales_item_ids).")");

    $items_details = array();

    while($row = mysql_fetch_assoc($items)) {
        $items_details[$row['id']]    = $row;                
    } 

    if($arr['shipping_address']){
        $shipping_address['address']    = $customer['address'];
        $shipping_address['city']       = $customer['city'];
        $shipping_address['state']      = $customer['state'];
        $shipping_address['country']    = $customer['country'];
        $shipping_address['zipcode']    = $customer['zipcode'];
    }else{
        $shipping_address['address']    = $arr['address'];
        $shipping_address['city']       = $arr['city'];
        $shipping_address['state']      = $arr['state'];
        $shipping_address['country']    = $arr['country'];
        $shipping_address['zipcode']    = $arr['zipcode'];
    }

    $item_xml = "";

    foreach($sales_items_arr as $item_id => $item_detail){
        $item_xml .= '<InvoiceLineAdd>
            <ItemRef>
                <ListID >'.$items_details[$item_id]['quickbooks_listid'].'</ListID>
                <FullName >'.$items_details[$item_id]['fullname'].'</FullName>
            </ItemRef>
            <Desc >'.$items_details[$item_id]['description'].'</Desc>
            <Quantity >'.$sales_items_arr[$item_id]['quantity'].'</Quantity>                    
            <Rate >'.$items_details[$item_id]['sales_price'].'</Rate>
        </InvoiceLineAdd>';
    }

    $xml = '<?xml version="1.0" encoding="utf-8"?>
<?qbxml version="11.0"?>
<QBXML>
    <QBXMLMsgsRq onError="continueOnError">
        <InvoiceAddRq>
            <InvoiceAdd>
                <CustomerRef>
                    <ListID >'.$customer['quickbooks_listid'].'</ListID>
                    <FullName >'.$customer['fullname'].'</FullName> 
                </CustomerRef>  
                <TxnDate >'.$arr['date'].'</TxnDate>
                <BillAddress>
                    <Addr1 >'.$customer['address'].'</Addr1>                    
                    <City >'.$customer['city'].'</City>                     
                </BillAddress>
                <ShipAddress>
                    <Addr1 >'.$customer['address'].'</Addr1>                    
                    <City >'.$customer['city'].'</City>                                                          
                </ShipAddress>    
                '.$item_xml.'
            </InvoiceAdd>
        </InvoiceAddRq>
    </QBXMLMsgsRq>
</QBXML>';

    return $xml;
}

function _quickbooks_invoice_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents){	
	mysql_query("
		UPDATE 
			customer_sales_order 
		SET 
			quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "',
                        reference_number = '". mysql_real_escape_string($idents['RefNumber']) ."',
			quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
		WHERE 
			id = " . (int) $ID);
}