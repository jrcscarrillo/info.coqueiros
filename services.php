<?php

require_once dirname(__FILE__) . '/config.php';

function send_mail($sales_order_object){
    
    $mail_obj               = array();
    $mail_obj['from']       = 'noreply@quickbooks.com';
    
    if(isset($sales_order_object['cc'])) {
        $mail_obj['to_email']   = 'jrcscarrillo@gmail.com,'.$sales_order_object['cc'];
    } else {
        $mail_obj['to_email']   = 'jrcscarrillo@gmail.com';
    }
    $mail_obj['subject']    = 'Sales Order Details';
    

    if(!empty($sales_order_object['sales_order']['signature'])){
        $myfile                 = fopen("signature.png", "w");
        fwrite($myfile, base64_decode($sales_order_object['sales_order']['signature']));
        fclose($myfile);
        $mail_obj['filename']   = 'signature.png';
        $mail_obj['content']    = file_get_contents("signature.png");
        $size                   = filesize("signature.png");
    }
  
    $address            = $sales_order_object['customer']['address'].'<br/>'.$sales_order_object['customer']['city'].'<br/>'.$sales_order_object['customer']['zipcode'].'<br/>'.$sales_order_object['customer']['country'].'<br/>'.$sales_order_object['customer']['state'];
    $shipping_address   = $sales_order_object['customer']['address'].'<br/>'.$sales_order_object['customer']['city'].'<br/>'.$sales_order_object['customer']['zipcode'].'<br/>'.$sales_order_object['customer']['country'].'<br/>'.$sales_order_object['customer']['state'];
    
    if(!$sales_order_object['sales_order']['shipping_address']){
        $shipping_address   = $sales_order_object['sales_order']['address'].'<br/>'.$sales_order_object['sales_order']['city'].'<br/>'.$sales_order_object['sales_order']['zipcode'].'<br/>'.$sales_order_object['sales_order']['country'].'<br/>'.$sales_order_object['sales_order']['state'];
    }
    
    $mail_obj['body']       = "";
    $mail_obj['body']      .= "<table border='1' cellspacing='0' cellpadding='7'>";
    $mail_obj['body']      .= "<tr>";
    $mail_obj['body']      .= "<td>Name</td>";   
    $mail_obj['body']      .= "<td colspan='4'>".$sales_order_object['customer']['name']."</td>";   
    $mail_obj['body']      .= "</tr>";
    $mail_obj['body']      .= "<tr>";
    $mail_obj['body']      .= "<td>Address</td>";
    $mail_obj['body']      .= "<td colspan='4'>".$address."</td>";   
    $mail_obj['body']      .= "</tr>";
    $mail_obj['body']      .= "<tr>";
    $mail_obj['body']      .= "<td>Shipping Address</td>";
    $mail_obj['body']      .= "<td colspan='4'>".$shipping_address."</td>";   
    $mail_obj['body']      .= "</tr>";
    $mail_obj['body']      .= "<tr>";
    $mail_obj['body']      .= "<td  colspan='5' align='center'>Items</td>";    
    $mail_obj['body']      .= "</tr>";
    $mail_obj['body']      .= "<tr>";
    $mail_obj['body']      .= "<td>Name</td>";
    $mail_obj['body']      .= "<td>Description</td>";    
    $mail_obj['body']      .= "<td>Quantity</td>";   
    $mail_obj['body']      .= "<td>Unit of Measure</td>"; 
    $mail_obj['body']      .= "<td>Price</td>";     
    $mail_obj['body']      .= "</tr>";
    
    $total = 0;
    foreach($sales_order_object['item'] as $item){
        $total  += $item['quantity'] * $item['sales_price'];
        
        $mail_obj['body']      .= "<tr>";
        $mail_obj['body']      .= "<td>".$item['fullname']."</td>";
        $mail_obj['body']      .= "<td>".$item['description']."</td>";
        $mail_obj['body']      .= "<td>".$item['quantity']."</td>";   
        $mail_obj['body']      .= "<td>".$item['base_unit_abbreviation']."</td>"; 
        $mail_obj['body']      .= "<td>".$item['quantity']*$item['sales_price']."</td>";        
        $mail_obj['body']      .= "</tr>";
    }
    
    $mail_obj['body']          .= "<tr>";
    $mail_obj['body']          .= "<td  colspan='4' align='right'>Total</td>";    
    $mail_obj['body']          .= "<td>".$total."</td>";    
    $mail_obj['body']          .= "</tr>";
    $mail_obj['body']          .= "</table>";

    if(!empty($sales_order_object['sales_order']['note'])){
        $mail_obj['body']          .= '<p style="color:red">';
        $mail_obj['body']          .= 'Note: '.$sales_order_object['sales_order']['note'];
        $mail_obj['body']          .= '</p>';
    }

    $mail_obj['content_type']   = 'text/html; charset=iso-8859-1';

    
    $headers  = 'From: '. $mail_obj['from'] . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $eol      = "\r\n";

    if(isset($mail_obj['filename']) && !empty($mail_obj['filename'])){
                   
        $boundary   = md5(uniqid(time()));   
        $attachment = chunk_split(base64_encode($mail_obj['content']));
        
        $headers .= "Content-Type: multipart/mixed; boundary=\"".$boundary."\"";
        
        $message_string  = "--".$boundary."\r\n";
        $message_string .= "Content-Transfer-Encoding: 7bit"."\r\n"."\r\n";
        $message_string .= "This is a MIME encoded message."."\r\n";
        $message_string .= "--".$boundary.$eol;
        $message_string .= "Content-Type: text/html; charset=\"iso-8859-1\"".$eol;
        $message_string .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
        $message_string .= $mail_obj['body'].$eol;
        $message_string .= "--".$boundary.$eol;
        $message_string .= "Content-Type: application/octet-stream; name=\"".$mail_obj['filename']."\"".$eol; 
        $message_string .= "Content-Transfer-Encoding: base64".$eol;
        $message_string .= "Content-Disposition: attachment".$eol.$eol;
        $message_string .= $attachment.$eol;
        $message_string .= "--".$boundary."--";


    }else{
        $headers        .= 'Content-type:' . $mail_obj['content_type'] . "\r\n";
        $message_string  = $mail_obj['body'];
    }
    
    // Mail it
    mail($mail_obj['to_email'], $mail_obj['subject'], $message_string, $headers);        
    unlink("signature.png");
}

if($_REQUEST){
    
    $action = isset($_REQUEST['action'])?$_REQUEST['action']:'';

    switch ($action){

        case 'customer_list':
            
            $customers   = array();
            $query       = "SELECT * FROM my_customer_table";      
            if(isset($_REQUEST['s']) && !empty($_REQUEST['s'])){
                $query      .= " WHERE name LIKE '%{$_REQUEST['s']}%'";
            }
            $array      = mysql_query($query);
            
            while($row = mysql_fetch_assoc($array)) {
                $sale_tax_code  = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$row['sales_tax_code_ref_listid']}'"));
                $item_tax_code  = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$row['item_sales_tax_ref_listid']}'"));               
                $customer_details   = $row;
                $customer_details['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                $customer_details['item_tax_code_name']                     = $sale_tax_code['name'];
                $customer_details['item_tax_code_description']              = $sale_tax_code['description'];
                $customer_details['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                $customer_details['customer_tax_code_name']                 = $item_tax_code['name'];
                $customer_details['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                $customer_details['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];
                       
                $customers[]        = $customer_details;
            }
            
            $message    = array(
                'message'   => 'Customer List',
                'status'    => 'success',
                'data'      => $customers
            );
            
            echo json_encode($message);
            die();
            
            break;
        
        case 'customer_add':
            
            if(!isset($_POST) && empty($_POST)){
                $message    = array(
                    'message'   => 'Request is not post',
                    'status'    => 'error',  
                    'data'      => array('customer' => NULL)
                ); 

                echo json_encode($message);
            } else {

                $_REQUEST['city']       = (!isset($_REQUEST['city']))?'':$_REQUEST['city'];
                $_REQUEST['salutation'] = (!isset($_REQUEST['salutation']))?'':$_REQUEST['salutation'];
                $_REQUEST['state']      = (!isset($_REQUEST['state']))?'':$_REQUEST['state'];
                $_REQUEST['country']    = (!isset($_REQUEST['country']))?'':$_REQUEST['country'];
                $_REQUEST['zipcode']    = (!isset($_REQUEST['zipcode']))?'':$_REQUEST['zipcode'];

                mysql_query(" INSERT INTO my_customer_table (name,fname,lname,salutation,address,city,state,zipcode,country,email)                     
                
                VALUES (
                        '" . mysql_real_escape_string($_REQUEST['name']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['fname']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['lname']) . "',
                        '" . mysql_real_escape_string($_REQUEST['salutation']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['address']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['city']) . "',
                        '" . mysql_real_escape_string($_REQUEST['state']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['zipcode']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['country']) . "',
                        '" . mysql_real_escape_string($_REQUEST['email']) . "'
                )");

                // Get the primary key of the new record
                $id = mysql_insert_id();

                // Queue up the customer add 
                $Queue = new QuickBooks_WebConnector_Queue($dsn);
                $Queue->enqueue(QUICKBOOKS_ADD_CUSTOMER, $id);
                
                $customer_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = {$id}"));
                $sale_tax_code      = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$customer_details['sales_tax_code_ref_listid']}'"));
                $item_tax_code      = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$customer_details['item_sales_tax_ref_listid']}'"));                               
                $customer_details['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                $customer_details['item_tax_code_name']                     = $sale_tax_code['name'];
                $customer_details['item_tax_code_description']              = $sale_tax_code['description'];
                $customer_details['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                $customer_details['customer_tax_code_name']                 = $item_tax_code['name'];
                $customer_details['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                $customer_details['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];
                
                $message    = array(
                    'message'   => 'Customer Added Successfully.',
                    'status'    => 'success',  
                    'data'      => array('customer' => $customer_details)
                ); 

                echo json_encode($message);
            }
            
            die();

            break;

        case 'customer_edit':            
            
            if(isset($_POST['name'])){

                $_REQUEST['city']       = (!isset($_REQUEST['city']))?'':$_REQUEST['city'];
                $_REQUEST['salutation'] = (!isset($_REQUEST['salutation']))?'':$_REQUEST['salutation'];
                $_REQUEST['state']      = (!isset($_REQUEST['state']))?'':$_REQUEST['state'];
                $_REQUEST['country']    = (!isset($_REQUEST['country']))?'':$_REQUEST['country'];
                $_REQUEST['zipcode']    = (!isset($_REQUEST['zipcode']))?'':$_REQUEST['zipcode'];

                mysql_query("
                    UPDATE my_customer_table SET
                        name        = '".mysql_real_escape_string($_POST['name'])."',
                        fname       = '".mysql_real_escape_string($_POST['fname'])."',
                        lname       = '".mysql_real_escape_string($_POST['lname'])."',                       
                        address     = '".mysql_real_escape_string($_POST['address'])."',
                        city        = '".mysql_real_escape_string($_POST['city'])."',
                        state       = '".mysql_real_escape_string($_POST['state'])."',
                        zipcode     = '".mysql_real_escape_string($_POST['zipcode'])."',
                        email       = '".mysql_real_escape_string($_POST['email'])."',
                        country     = '".mysql_real_escape_string($_POST['country'])."'
                        WHERE id = ".mysql_real_escape_string($_POST['id'])."

                ");


                // Queue up the customer modification 
                $Queue = new QuickBooks_WebConnector_Queue($dsn);
                $Queue->enqueue(QUICKBOOKS_MOD_CUSTOMER, $_POST['id']);

                $message    = array(
                    'message'   => 'Customer Updated Successfully.',
                    'status'    => 'success',   
                    'data'      => array('customer' => NULL)
                );
                
            }else{
                
                $customer_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = {$_REQUEST['id']}"));
                $sale_tax_code      = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$customer_details['sales_tax_code_ref_listid']}'"));
                $item_tax_code      = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$customer_details['item_sales_tax_ref_listid']}'"));                               
                $customer_details['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                $customer_details['item_tax_code_name']                     = $sale_tax_code['name'];
                $customer_details['item_tax_code_description']              = $sale_tax_code['description'];
                $customer_details['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                $customer_details['customer_tax_code_name']                 = $item_tax_code['name'];
                $customer_details['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                $customer_details['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];
                
                $message    = array(
                    'message'   => 'Customer Details.',
                    'status'    => 'success',   
                    'data'      => array('customer' => $customer_details)
                );
            }
            
            echo json_encode($message);
            die();

            break;
            
        case 'item_add':            
            
            if(!isset($_POST) && empty($_POST)){
                $message    = array(
                    'message'   => 'Request is not post',
                    'status'    => 'error',  
                    'data'      => array('item' => NULL)
                ); 

                echo json_encode($message);
            } else {
                mysql_query(" INSERT INTO items (name,amount,description) 
                    
                VALUES (
                        '" . mysql_real_escape_string($_REQUEST['name']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['amount']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['description']) . "'                        
                )");

                // Get the primary key of the new record
                $id = mysql_insert_id();

                // Queue up the customer add 
                $Queue = new QuickBooks_WebConnector_Queue($dsn);
                $Queue->enqueue(QUICKBOOKS_ADD_SERVICEITEM, $id);
                
                $customer_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM items WHERE id = {$id}"));
                
                $message    = array(
                    'message'   => 'Item Added Successfully.',
                    'status'    => 'success',  
                    'data'      => array('item' => $customer_details)
                ); 

                echo json_encode($message);
            }
            
            die();
            
            break;
        
        case 'item_list':            
            
            $array              = mysql_query("SELECT * FROM items");
            $unit_of_measure    = array();
            while($row  = mysql_fetch_assoc($array)) {
                $unit_of_measure    = mysql_fetch_assoc(mysql_query("SELECT * FROM unit_of_meaures WHERE quickbooks_listid = '{$row['unit_of_measure_set_ref_listid']}'"));
                unset($unit_of_measure['id']);
                $items[]            = is_array($unit_of_measure)?array_merge($row,$unit_of_measure):$row;                
            }
            
            $units      = mysql_query("SELECT * FROM unit_of_meaures");
            while($row  = mysql_fetch_assoc($units)) {
                $unit_of_measures[]   =  $row;
            }            
            $message    = array(
                'message'   => 'Item List',
                'status'    => 'success',
                'data'      => array(
                    'items'             => $items,
                    'unit_of_measures'  => $unit_of_measures
                )
            );
            
            echo json_encode($message);
            die();

            break;
            
        case 'sales_order_list':            
            
            $sales_order_query  = "SELECT * FROM customer_sales_order";
            $customer_query     = "SELECT * FROM my_customer_table";
            $item_query         = "SELECT * FROM items";
            
            if(isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])){
                $sales_order_query  .= " WHERE customer_id = {$_REQUEST['customer_id']}";
                $customer_query     .= " WHERE id = {$_REQUEST['customer_id']}";
            }
            
            $array              = mysql_query($sales_order_query);
            $item_detail        = mysql_query($item_query);
            
            while($row = mysql_fetch_assoc($item_detail)) {                
                $unit_of_measure    = mysql_fetch_assoc(mysql_query("SELECT * FROM unit_of_meaures WHERE quickbooks_listid = '{$row['unit_of_measure_set_ref_listid']}'"));
                unset($unit_of_measure['id']);
                $items[$row['id']]  = is_array($unit_of_measure)?array_merge($row,$unit_of_measure):$row;   
            }
            $item_tax_code  = mysql_query("SELECT * FROM tax_codes");
            
            while($row = mysql_fetch_assoc($item_tax_code)){
                $item_tax[$row['quickbooks_listid']] =  $row['name'];
            }
            
            while($row = mysql_fetch_assoc($array)) {                 
                $sales_order_items          = mysql_query("SELECT * FROM sales_order_items where sales_order_id = {$row['id']}");
                $sale_order_items_details   = array();
                $i = 0;
                while($sales_item = mysql_fetch_assoc($sales_order_items)) {   
                    $sale_order_items_details[$i]               = array_merge($sales_item,$items[$sales_item['item_id']]);
                    $sale_order_items_details[$i]['tx_name']    = isset($item_tax[$sales_item['tx']])?$item_tax[$sales_item['tx']]:'';
                    $i++;
                }
          
                $sales_order[]      = array(
                    'sales_order'   => $row,
                    'items'         => $sale_order_items_details
                );
               
            }
            
            if(empty($sales_order)){
                $sales_order    = NULL;
            }
        
            $message    = array(
                'message'   => 'Item List',
                'status'    => 'success',
                'data'      => $sales_order             
            );
            
            echo json_encode($message);
            die();

            break;
            
        case 'sales_order_add':            
            
            if(!isset($_POST) && empty($_POST)){
                $message    = array(
                    'message'   => 'Request is not post',
                    'status'    => 'error',  
                    'data'      => array('sales' => NULL)
                ); 

                echo json_encode($message);
            } else if(isset($_POST['customer_id'])){
              
                if(!isset($_REQUEST['shipping_address'])){
                    $_REQUEST['shipping_address']   = FALSE;                    
                }
                
                if(isset($_REQUEST['shipping_address']) && ($_REQUEST['shipping_address'])){
                    $_REQUEST['address']            = NULL;
                    $_REQUEST['city']               = NULL;
                    $_REQUEST['state']              = NULL;
                    $_REQUEST['country']            = NULL;
                    $_REQUEST['zipcode']            = NULL;
                }
                
                $address    = isset($_POST['address'])?$_POST['address']:'';
                $city       = isset($_POST['city'])?$_POST['city']:'';
                $state      = isset($_POST['state'])?$_POST['state']:'';
                $country    = isset($_POST['country'])?$_POST['country']:'';
                $zipcode    = isset($_POST['zipcode'])?$_POST['zipcode']:'';
                
                if(!isset($_REQUEST['note'])){
                   $_REQUEST['note'] = '';
                }                

                mysql_query(" INSERT INTO customer_sales_order (customer_id,shipping_address,address,city,state,country,zipcode,date,tax_type,signature,note) 
                
                VALUES (
                        '" . mysql_real_escape_string($_REQUEST['customer_id']) . "',                         
                        '" . mysql_real_escape_string($_REQUEST['shipping_address']) . "',
                        '" . mysql_real_escape_string($address) . "',
                        '" . mysql_real_escape_string($city) . "',
                        '" . mysql_real_escape_string($state) . "',
                        '" . mysql_real_escape_string($country) . "',
                        '" . mysql_real_escape_string($zipcode) . "',
                        '" . date('Y-m-d',time()). "',
                        '" . mysql_real_escape_string($_REQUEST['tax_type']) . "',
                        '" . mysql_real_escape_string($_REQUEST['signature']) . "',
                        '" . mysql_real_escape_string($_REQUEST['note']) . "'                        
                )");

                // Get the primary key of the new record
                $id     = mysql_insert_id();
             
                $query  = "INSERT INTO sales_order_items (sales_order_id,item_id,quantity,description,unit_of_measure,tx) VALUES ";
                
                $queryStr   = array();
                
                $tax_codes      = mysql_query("SELECT * FROM tax_codes");
            
                while($row = mysql_fetch_assoc($tax_codes)){
                    $item_tax[$row['quickbooks_listid']] =  $row['name'];
                }
                
                for($i=0;$i<count($_REQUEST['item_id']);$i++){                    
                    if(!empty($_REQUEST['quantity'][$i]) || ($_REQUEST['quantity'][$i] != 0)){
                        $item_ids[]         = $_REQUEST['item_id'][$i];                          
                        $sales_order_items[$_REQUEST['item_id'][$i]] = array(                            
                            'item_id'           => $_REQUEST['item_id'][$i],
                            'quantity'          => $_REQUEST['quantity'][$i],
                            'description'       => $_REQUEST['description'][$i],
                            'unit_of_measure'   => $_REQUEST['unit_of_measure'][$i],
                            'tx'                => $_REQUEST['tx'][$i],
                            'tx_name'           => isset($item_tax[$_REQUEST['tx'][$i]])?$item_tax[$_REQUEST['tx'][$i]]:''
                        );
                        $queryStr[] = "(
                            '" .$id. "',
                            '" . mysql_real_escape_string($_REQUEST['item_id'][$i]) . "', 
                            '" . mysql_real_escape_string($_REQUEST['quantity'][$i]) . "',
                            '" . mysql_real_escape_string($_REQUEST['description'][$i]) . "',
                            '" . mysql_real_escape_string($_REQUEST['unit_of_measure'][$i]) . "',
                            '" . mysql_real_escape_string($_REQUEST['tx'][$i]) . "'
                        )";
                    }                    
                }
                
                
                $query  .= implode(',',$queryStr);
                $query  .= ";";
                
                
                mysql_query($query);
                
                // Queue up the customer add 
                $Queue = new QuickBooks_WebConnector_Queue($dsn);
                $Queue->enqueue(QUICKBOOKS_ADD_SALESORDER, $id);
                
                $items                 = array();
                $sales_order_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM customer_sales_order WHERE id = {$id}"));
                $customer_detail       = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = {$_REQUEST['customer_id']}"));
                $sale_tax_code         = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$customer_detail['sales_tax_code_ref_listid']}'"));
                $item_tax_code         = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$customer_detail['item_sales_tax_ref_listid']}'"));                               
                $customer_detail['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                $customer_detail['item_tax_code_name']                     = $sale_tax_code['name'];
                $customer_detail['item_tax_code_description']              = $sale_tax_code['description'];
                $customer_detail['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                $customer_detail['customer_tax_code_name']                 = $item_tax_code['name'];
                $customer_detail['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                $customer_detail['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];
                
                $item_detail    = mysql_query("SELECT * FROM items WHERE id IN (".implode(',',$item_ids).")");
                
                $i = 0;
                while($row = mysql_fetch_assoc($item_detail)) {                            
                    $unit_of_measure    = mysql_fetch_assoc(mysql_query("SELECT * FROM unit_of_meaures WHERE quickbooks_listid = '{$row['unit_of_measure_set_ref_listid']}'"));
                    unset($unit_of_measure['id']);
                    $item_arr               = array_merge($sales_order_items[$row['id']],$row);
                    $items[$i]              = is_array($unit_of_measure)?array_merge($item_arr,$unit_of_measure):$item_arr;                        
                    $i++;
                }
                
                $sales_order_object = array(
                    'sales_order'   => $sales_order_details,
                    'customer'      => $customer_detail,
                    'item'          => $items,
                    'cc'            => (isset($_REQUEST['cc'])) ? $_REQUEST['cc'] : '',
                );
                $sales_message  = 'Sales Order Added Successfully.';
                
                if(isset($_REQUEST['send_email']) && !empty($_REQUEST['send_email'])  && ($_REQUEST['send_email'])){
                    send_mail($sales_order_object);
                    $sales_message  = 'Sales Order Added Successfully And Mail Sent to the company.';
                }
                
                $message    = array(
                    'message'   => $sales_message,
                    'status'    => 'success',  
                    'data'      => $sales_order_object
                ); 

                echo json_encode($message);
            }else{
                
                $customer_detail       = mysql_query("SELECT * FROM my_customer_table");
                $item_detail           = mysql_query("SELECT * FROM items");
                
                while($row = mysql_fetch_assoc($customer_detail)) {
                    $sale_tax_code  = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$row['sales_tax_code_ref_listid']}'"));
                    $item_tax_code  = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$row['item_sales_tax_ref_listid']}'"));               
                    $customer_details   = $row;
                    $customer_details['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                    $customer_details['item_tax_code_name']                     = $sale_tax_code['name'];
                    $customer_details['item_tax_code_description']              = $sale_tax_code['description'];
                    $customer_details['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                    $customer_details['customer_tax_code_name']                 = $item_tax_code['name'];
                    $customer_details['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                    $customer_details['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];

                    $customers[]        = $customer_details;
                }

                while($row = mysql_fetch_assoc($item_detail)) {
                    $items[]    = $row;
                }
                
                $message    = array(
                    'message'   => 'Request is not post',
                    'status'    => 'error',  
                    'data'      => array(
                        'customer'      => $customers,
                        'item'          => $items
                    )
                ); 
                
                echo json_encode($message);
            }
            
            die();

            break;
            
        case 'sales_order_edit':
            
            if(isset($_REQUEST['customer_id']) && ($_REQUEST['customer_id'] != 0)){
                
                if(!isset($_REQUEST['shipping_address'])){
                    $_REQUEST['shipping_address']   = FALSE;                    
                }
                
                if(isset($_REQUEST['shipping_address']) && ($_REQUEST['shipping_address'])){
                    $_REQUEST['address']            = NULL;
                    $_REQUEST['city']               = NULL;
                    $_REQUEST['state']              = NULL;
                    $_REQUEST['country']            = NULL;
                    $_REQUEST['zipcode']            = NULL;
                }
                
                $address    = isset($_POST['address'])?$_POST['address']:'';
                $city       = isset($_POST['city'])?$_POST['city']:'';
                $state      = isset($_POST['state'])?$_POST['state']:'';
                $country    = isset($_POST['country'])?$_POST['country']:'';
                $zipcode    = isset($_POST['zipcode'])?$_POST['zipcode']:'';
                  
                if(!isset($_POST['note'])){
                   $_POST['note'] = '';
                } 
                
                mysql_query("
                    UPDATE customer_sales_order SET
                        customer_id         = '".mysql_real_escape_string($_POST['customer_id'])."',                        
                        shipping_address    = '".mysql_real_escape_string($_POST['shipping_address'])."',
                        address             = '".mysql_real_escape_string($address)."',
                        city                = '".mysql_real_escape_string($city)."',
                        state               = '".mysql_real_escape_string($state)."',
                        country             = '".mysql_real_escape_string($country)."',
                        tax_type            = '".mysql_real_escape_string($_POST['tax_type'])."',
                        signature           = '".mysql_real_escape_string($_POST['signature'])."',
                        zipcode             = '".mysql_real_escape_string($zipcode)."',
                        note                = '".mysql_real_escape_string($_POST['note'])."'
                        WHERE id = ".mysql_real_escape_string($_REQUEST['id'])."

                ");
                
                
                $delete_query   = "DELETE FROM sales_order_items WHERE sales_order_id = {$_REQUEST['id']}";
                mysql_query($delete_query);

                $query      = "INSERT INTO sales_order_items (sales_order_id,item_id,quantity,description,unit_of_measure,tx) VALUES ";                
                $queryStr   = array();
                $tax_codes  = mysql_query("SELECT * FROM tax_codes");
            
                while($row = mysql_fetch_assoc($tax_codes)){
                    $item_tax[$row['quickbooks_listid']] =  $row['name'];
                }
                for($i=0;$i<count($_REQUEST['item_id']);$i++){                    
                    if(!empty($_REQUEST['quantity'][$i]) || ($_REQUEST['quantity'][$i] != 0)){    
                        $item_ids[] = $_REQUEST['item_id'][$i];
                        $sales_order_items[$_REQUEST['item_id'][$i]] = array(
                            'item_id'           => $_REQUEST['item_id'][$i],
                            'quantity'          => $_REQUEST['quantity'][$i],
                            'description'       => $_REQUEST['description'][$i],
                            'unit_of_measure'   => $_REQUEST['unit_of_measure'][$i],
                            'tx'                => $_REQUEST['tx'][$i],
                            'tx_name'           => isset($item_tax[$_REQUEST['tx'][$i]])?$item_tax[$_REQUEST['tx'][$i]]:''
                        );
                        $queryStr[] = "(
                            '" .$_POST['id']. "',
                            '" . mysql_real_escape_string($_REQUEST['item_id'][$i]) . "', 
                            '" . mysql_real_escape_string($_REQUEST['quantity'][$i]) . "',
                            '" . mysql_real_escape_string($_REQUEST['description'][$i]) . "',
                            '" . mysql_real_escape_string($_REQUEST['unit_of_measure'][$i]) . "',
                            '" . mysql_real_escape_string($_REQUEST['tx'][$i]) . "'
                        )";
                    }                    
                }
                
                $query  .= implode(',',$queryStr);
                $query  .= ";";
                
                mysql_query($query);
                
                // Queue up the customer add 
//                $Queue = new QuickBooks_WebConnector_Queue($dsn);
//                $Queue->enqueue(QUICKBOOKS_MOD_INVOICE, $id);

                $items                 = array();
                $sales_order_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM customer_sales_order WHERE id = {$_REQUEST['id']}"));
                $customer_detail       = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = {$_REQUEST['customer_id']}"));
                $sale_tax_code         = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$customer_detail['sales_tax_code_ref_listid']}'"));
                $item_tax_code         = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$customer_detail['item_sales_tax_ref_listid']}'"));                               
                $customer_detail['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                $customer_detail['item_tax_code_name']                     = $sale_tax_code['name'];
                $customer_detail['item_tax_code_description']              = $sale_tax_code['description'];
                $customer_detail['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                $customer_detail['customer_tax_code_name']                 = $item_tax_code['name'];
                $customer_detail['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                $customer_detail['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];
                $item_detail           = mysql_query("SELECT * FROM items WHERE id IN (".implode(',',$item_ids).")");
                
                while($row = mysql_fetch_assoc($item_detail)) {  
                    $unit_of_measure    = mysql_fetch_assoc(mysql_query("SELECT * FROM unit_of_meaures WHERE quickbooks_listid = '{$row['unit_of_measure_set_ref_listid']}'"));
                    unset($unit_of_measure['id']);
                    $item_arr   = array_merge($sales_order_items[$row['id']],$row);
                    $items[]    = is_array($unit_of_measure)?array_merge($item_arr,$unit_of_measure):$item_arr;                    
                }
                
                $sales_order_object = array(
                    'sales_order'   => $sales_order_details,
                    'customer'      => $customer_detail,
                    'item'          => $items,
                    'cc'            => (isset($_REQUEST['cc'])) ? $_REQUEST['cc'] : '',
                );
                $sales_message  = 'Sales Order Updated Successfully.';
                if(isset($_REQUEST['send_email']) && !empty($_REQUEST['send_email'])  && ($_REQUEST['send_email'])){
                    send_mail($sales_order_object);
                    $sales_message  = 'Sales Order Updated Successfully And Mail Sent to the comapany.';
                }
                
                $message    = array(
                    'message'   => $sales_message,
                    'status'    => 'success',  
                    'data'      => $sales_order_object
                ); 
                 
            }else{
                
                $customer_detail    = mysql_query("SELECT * FROM customer_sales_order");
                $item_detail        = mysql_query("SELECT * FROM items");
                
                while($row = mysql_fetch_assoc($customer_detail)) {
                    $sale_tax_code  = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$row['sales_tax_code_ref_listid']}'"));
                    $item_tax_code  = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$row['item_sales_tax_ref_listid']}'"));               
                    $customer_details   = $row;
                    $customer_details['item_tax_code_quickbooks_listid']        = $sale_tax_code['quickbooks_listid'];
                    $customer_details['item_tax_code_name']                     = $sale_tax_code['name'];
                    $customer_details['item_tax_code_description']              = $sale_tax_code['description'];
                    $customer_details['customer_tax_code_quickbooks_listid']    = $item_tax_code['quickbooks_listid'];
                    $customer_details['customer_tax_code_name']                 = $item_tax_code['name'];
                    $customer_details['customer_tax_code_item_desc']            = $item_tax_code['item_desc'];
                    $customer_details['customer_tax_code_tax_rate']             = $item_tax_code['tax_rate'];

                    $customers[]        = $customer_details;
                }

                while($row = mysql_fetch_assoc($item_detail)) {
                    $items[]    = $row;
                }
                
                $message    = array(
                    'message'   => 'Sales Details',
                    'status'    => 'error',   
                    'data'      => array(
                        'customer' => $customers,
                        'item'     => $items
                    )
                );
            }
            
            echo json_encode($message);
            die();
            
            break;
        
        case 'user_add':
            
            if(!isset($_POST) && empty($_POST)){
                $message    = array(
                    'message'   => 'Request is not post',
                    'status'    => 'error',  
                    'data'      => array('user' => NULL)
                ); 

                echo json_encode($message);
                
            } else if(!empty($_REQUEST['email_id']) && !empty($_REQUEST['password'])){
                
                
                $user_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM users WHERE email_id = '{$_REQUEST['email_id']}'"));
                
                
                if($user_details){
                    $message    = array(
                        'message'   => 'User already exists.',
                        'status'    => 'error',  
                        'data'      => array('user' => NULL)
                    ); 

                    echo json_encode($message);
                    
                    die();
                }
                
                mysql_query(" INSERT INTO users (email_id,password,first_name,last_name,created) 
                    
                VALUES (
                        '" . mysql_real_escape_string($_REQUEST['email_id']) . "', 
                        '" . md5($_REQUEST['password']) . "', 
                        '" . mysql_real_escape_string($_REQUEST['first_name']) . "',
                        '" . mysql_real_escape_string($_REQUEST['last_name']) . "',   
                        '" . date('Y-m-d H:i:s',time()) . "'    
                )");

                // Get the primary key of the new record
                $id = mysql_insert_id();

                // Queue up the customer add 
                
                $user_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM users WHERE id = {$id}"));
                
                $message    = array(
                    'message'   => 'User Register Successfully.',
                    'status'    => 'success',  
                    'data'      => array('user' => $user_details)
                ); 

                echo json_encode($message);
            }else{
                 $message    = array(
                    'message'   => 'Please Fill up deatails.',
                    'status'    => 'error',  
                    'data'      => array('user' => NULL)
                ); 

                echo json_encode($message);
            }
            
            die();
            
            break;
            
        case 'user_edit':
            
            if(isset($_POST['first_name'])){
                
                $query   = "UPDATE users SET
                            first_name  = '".mysql_real_escape_string($_POST['first_name'])."',
                            last_name   = '".mysql_real_escape_string($_POST['last_name'])."',
                            username    = '".mysql_real_escape_string($_POST['username'])."'";
                
                if(!empty($_POST['password'])){
                  $query    .= ", password    = '".md5($_POST['password'])."'";
                }
                
                $query    .= " WHERE id    = ".mysql_real_escape_string($_POST['id']);
                
                mysql_query($query);
                
                $user   = mysql_fetch_assoc(mysql_query("SELECT * FROM users WHERE id = {$_REQUEST['id']}"));
              
                $message    = array(
                    'message'   => 'User Updated Successfully.',
                    'status'    => 'success',   
                    'data'      => array('user' => $user)
                );
                
            }else{
                
                $user   = mysql_fetch_assoc(mysql_query("SELECT * FROM users WHERE id = {$_REQUEST['id']}"));
                
                $message    = array(
                    'message'   => 'User Details.',
                    'status'    => 'success',   
                    'data'      => array('user' => $user)
                );
            }
            
            echo json_encode($message);
            
            die();
            
            break;            
            
        case 'user_login':
            
             if(!isset($_POST) && empty($_POST)){
                $message    = array(
                    'message'   => 'Request is not post',
                    'status'    => 'error',  
                    'data'      => array()
                ); 

                echo json_encode($message);
                
            } else {
                
                $user_details   = mysql_fetch_assoc(mysql_query("SELECT * FROM users WHERE email_id = '{$_REQUEST['email_id']}' AND password ='".md5($_REQUEST['password'])."'"));
                
                if($user_details){
                    $message    = array(
                        'message'   => 'You are Logged In Successfully.',
                        'status'    => 'success',  
                        'data'      => array('user' => $user_details)
                    ); 

                    echo json_encode($message);
                }else{
                    $message    = array(
                        'message'   => 'You Cannot Logged in.Please Enter Correct email Id or password.',
                        'status'    => 'error',  
                        'data'      => array('user' => NULL)
                    ); 

                    echo json_encode($message);
                }
            }
            
            die();
            
            break;
            
        case 'sale_tax_codes_list':
            
                $query       = "SELECT * FROM tax_codes";      
                $array       = mysql_query($query);

                while($row = mysql_fetch_assoc($array)) {
                    $tax_codes[]    = $row;
                }

                $message    = array(
                    'message'   => 'Sales Tax Codes',
                    'status'    => 'success',
                    'data'      => array(
                        'sales_tax_codes'   => $tax_codes
                    )
                );

                echo json_encode($message);
                die();
            break;  
            
        case 'customer_tax_codes_list':
            
                $query       = "SELECT * FROM sale_tax_items";      
                $array       = mysql_query($query);

                while($row = mysql_fetch_assoc($array)) {
                    $tax_codes[]    = $row;
                }

                $message    = array(
                    'message'   => 'Customer Tax Codes',
                    'status'    => 'success',
                    'data'      => array(
                        'customer_tax_codes'   => $tax_codes
                    )
                );

                echo json_encode($message);
                die();
            break;  
        
        case 'generate_report':
            
            $sales_order_query  = "SELECT * FROM customer_sales_order";
            $customer_query     = "SELECT * FROM my_customer_table";
            $item_query         = "SELECT * FROM items";
            
            if(isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])){
                $sales_order_query  .= " WHERE customer_id = {$_REQUEST['customer_id']}";
                $customer_query     .= " WHERE id = {$_REQUEST['customer_id']}";
            }
            
            $customer_query = mysql_query($customer_query);
            $customers      = array();
            
            while($row = mysql_fetch_assoc($customer_query)) {                
                 $customers[$row['id']] = $row;
            }
            
            if( isset($_REQUEST['from']) && isset($_REQUEST['to']) && 
                !empty($_REQUEST['from']) && !empty($_REQUEST['to'])){                
                $from               = date('Y-m-d',strtotime(str_replace('/', '-',$_REQUEST['from']))); 
                $to                 = date('Y-m-d',strtotime(str_replace('/', '-',$_REQUEST['to']))); 
                $sales_order_query  .= " WHERE date BETWEEN '{$from}' AND '{$to}'";
            }
            
            $array              = mysql_query($sales_order_query);
            $item_detail        = mysql_query($item_query);
            
            while($row = mysql_fetch_assoc($item_detail)) {                
                $unit_of_measure    = mysql_fetch_assoc(mysql_query("SELECT * FROM unit_of_meaures WHERE quickbooks_listid = '{$row['unit_of_measure_set_ref_listid']}'"));
                unset($unit_of_measure['id']);
                $items[$row['id']]  = is_array($unit_of_measure)?array_merge($row,$unit_of_measure):$row;   
            }
            $item_tax_code  = mysql_query("SELECT * FROM tax_codes");
            
            while($row = mysql_fetch_assoc($item_tax_code)){
                $item_tax[$row['quickbooks_listid']] =  $row['name'];
            }
            
            while($row = mysql_fetch_assoc($array)) {                 
                $sales_order_items          = mysql_query("SELECT * FROM sales_order_items where sales_order_id = {$row['id']}");
                $sale_order_items_details   = array();               
                $i = 0;
                $total  = 0;
                while($sales_item = mysql_fetch_assoc($sales_order_items)) {                       
                    $total                             += (float)$sales_item['quantity'] * (float)$items[$sales_item['item_id']]['sales_price'];                    
                    $row['total']                       = $total;
                    
                    $i++;
                }
                unset($row['signature']);
                $row['customer_name'] = $customers[$row['customer_id']]['name'];
                $sales_order[]      = array(
                    'sales_order'   => $row,                    
                );                
            }
                        
            if(empty($sales_order)){
                $sales_order    = NULL;
            }
        
            $message    = array(
                'message'   => 'Item List',
                'status'    => 'success',
                'data'      => $sales_order             
            );
            
         
            
            echo json_encode($message);
            die();
            
            break;
            
        case 'import_customer':
            
            $file           = 'customer.csv';    
            
            $query          = "INSERT INTO my_customer_table (
                name,fullname,fname,lname,salutation,address,city,state,zipcode,country,quickbooks_listid,quickbooks_editsequence,
                sales_rep_ref_listid,sales_rep_ref_fullname,sales_tax_code_ref_listid,sales_tax_code_ref_fullname,tax_code_ref_listid,
                tax_code_ref_fullname,item_sales_tax_ref_listid,item_sales_tax_ref_fullname
            ) VALUES ";            
           
            $queryStr   = array();
            $i = 0;
            if (($handle = fopen($file, "r")) !== FALSE) {
                 while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {                        
                     if($i > 1){                        
                         $queryStr[]  = "(
                                '" . mysql_real_escape_string($data[4]) . "', 
                                '" . mysql_real_escape_string($data[5]) . "', 
                                '" . mysql_real_escape_string($data[12]) . "',
                                '" . mysql_real_escape_string($data[14]) . "', 
                                '" . mysql_real_escape_string($data[11]) . "', 
                                '" . mysql_real_escape_string($data[15]) . "',
                                '" . mysql_real_escape_string($data[20]) . "', 
                                '" . mysql_real_escape_string($data[21]) . "', 
                                '" . mysql_real_escape_string($data[24]) . "',
                                '" . mysql_real_escape_string($data[25]) . "', 
                                '" . mysql_real_escape_string($data[0]) . "',
                                '" . mysql_real_escape_string($data[3]) . "',
                                '" . mysql_real_escape_string($data[59]) . "',
                                '" . mysql_real_escape_string($data[60]) . "',
                                '" . mysql_real_escape_string($data[65]) . "',
                                '" . mysql_real_escape_string($data[66]) . "',
                                '" . mysql_real_escape_string($data[67]) . "',
                                '" . mysql_real_escape_string($data[68]) . "',
                                '" . mysql_real_escape_string($data[69]) . "',
                                '" . mysql_real_escape_string($data[70]) . "'
                        )"; 
                     }
                     $i++;
                 }               
                 fclose($handle);
             }            
            $query  .= implode(',',$queryStr);
            $query  .= ";";
           
            mysql_query($query);
            die('process is completed.');
            
            break;
            
        case 'import_taxcode':
            
            $file           = 'salestaxitem.csv';    
            
            $query          = "INSERT INTO sale_tax_items (
                quickbooks_listid,quickbooks_editsequence,name,is_active,class_ref_listid,class_ref_fullname,is_used_on_purchase_transaction,
                item_desc,tax_rate,tax_vendaor_ref_listid,tax_vendor_ref_fullname,sales_tax_return_line_ref_listid,sales_tax_return_line_ref_fullname,external_GUID
            ) VALUES ";            
           
            $queryStr   = array();
            $i = 0;
            if (($handle = fopen($file, "r")) !== FALSE) {
                 while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {                            
                     if($i > 0){                        
                         $queryStr[]  = "(
                                '" . mysql_real_escape_string($data[0]) . "', 
                                '" . mysql_real_escape_string($data[3]) . "', 
                                '" . mysql_real_escape_string($data[4]) . "',
                                '" . mysql_real_escape_string($data[5]) . "', 
                                '" . mysql_real_escape_string($data[6]) . "', 
                                '" . mysql_real_escape_string($data[7]) . "',
                                '" . mysql_real_escape_string($data[8]) . "', 
                                '" . mysql_real_escape_string($data[9]) . "', 
                                '" . mysql_real_escape_string($data[10]) . "',
                                '" . mysql_real_escape_string($data[11]) . "',
                                '" . mysql_real_escape_string($data[12]) . "',
                                '" . mysql_real_escape_string($data[13]) . "',
                                '" . mysql_real_escape_string($data[14]) . "',
                                '" . mysql_real_escape_string($data[15]) . "'
                        )"; 
                     }
                     $i++;
                 }               
                 fclose($handle);
             }            
            $query  .= implode(',',$queryStr);
            $query  .= ";";
           
            mysql_query($query);
            die('process is completed.');
            
            break;
            
        case 'send_mail':
            
            if(isset($_REQUEST['id']) && !empty($_REQUEST['id'])){
                
                $sales_order_details    = mysql_fetch_assoc(mysql_query("SELECT * FROM customer_sales_order WHERE id = {$_REQUEST['id']}"));
                $customer_detail        = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = {$sales_order_details['customer_id']}"));
                $sale_tax_code          = mysql_fetch_assoc(mysql_query("SELECT * FROM tax_codes WHERE quickbooks_listid = '{$customer_detail['sales_tax_code_ref_listid']}'"));                
                $item_tax_code          = mysql_fetch_assoc(mysql_query("SELECT * FROM sale_tax_items WHERE quickbooks_listid = '{$customer_detail['item_sales_tax_ref_listid']}'"));
                $sales_order_item_list  = mysql_query("SELECT * FROM sales_order_items WHERE sales_order_id = {$_REQUEST['id']}"); 
                $item_ids               = array();
                $sales_order_items      = array();
                $tax_codes              = mysql_query("SELECT * FROM tax_codes");
            
                while($row = mysql_fetch_assoc($tax_codes)){
                    $item_tax[$row['quickbooks_listid']] =  $row['name'];
                }
                
                while($row = mysql_fetch_assoc($sales_order_item_list)) {  
                    $item_ids[]             = $row['item_id'];
                    $sales_order_items[$row['item_id']] = array(
                        'item_id'           => $row['item_id'],
                        'quantity'          => $row['quantity'],
                        'description'       => $row['description'],
                        'unit_of_measure'   => $row['unit_of_measure'],
                        'tx'                => $row['tx'],
                        'tx_name'           => isset($item_tax[$row['tx']])?$item_tax[$row['tx']]:''
                    );
                }
                
                $item_detail           = mysql_query("SELECT * FROM items WHERE id IN (".implode(',',$item_ids).")");
                
                while($row = mysql_fetch_assoc($item_detail)) {  
                    $unit_of_measure    = mysql_fetch_assoc(mysql_query("SELECT * FROM unit_of_meaures WHERE quickbooks_listid = '{$row['unit_of_measure_set_ref_listid']}'"));
                    unset($unit_of_measure['id']);
                    $item_arr   = array_merge($sales_order_items[$row['id']],$row);
                    $items[]    = is_array($unit_of_measure)?array_merge($item_arr,$unit_of_measure):$item_arr;                    
                }
                
                $sales_order_object = array(
                    'sales_order'   => $sales_order_details,
                    'customer'      => $customer_detail,
                    'item'          => $items
                );
                
                send_mail($sales_order_object);
            }
            
            die('complete');
            
            break;
        
        default :
            
            $message    = array(
                'message'   => 'No Request Found',
                'status'    => 'error',            
            );

            echo json_encode($message);
            die();
    }
}