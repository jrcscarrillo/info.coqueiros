<?php 
$action = 'action=customer_add';
if(isset($_REQUEST['id']) && !empty($_REQUEST['id'])){
    require_once dirname(__FILE__) . '/config.php';
    $array   = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = {$_REQUEST['id']}"));
    $action  = 'action=customer_edit';
}
?>
<html>
    <head>
        <title>QuickBooks Sales Order App</title>
    </head>
    <body>
        <form method="post" action="services.php<?php echo '?'.$action; ?>">                        
            <input type="hidden" name="submitted" value="1" />
            <?php if(isset($array['id']) && !empty($array['id'])){ ?>
            <input type="hidden" name="id" value="<?php echo $array['id']?>" />
            <?php } ?>
            <table>
                <tr>
                    <td>Company Name</td>
                    <td>
                        <input type="text" name="name" value="<?php echo (isset($array['name']) && !empty($array['name']))?$array['name']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <td>Salutation</td>
                    <td>
                        <input type="text" name="salutation" value="<?php echo (isset($array['salutation']) && !empty($array['salutation']))?$array['salutation']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <td>First Name</td>
                    <td>
                        <input type="text" name="fname" value="<?php echo (isset($array['fname']) && !empty($array['fname']))?$array['fname']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <td>Last Name</td>
                    <td>
                        <input type="text" name="lname" value="<?php echo (isset($array['lname']) && !empty($array['lname']))?$array['lname']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <th colspan="2" style="text-align: left;">Billing Address Details</th>
                </tr>
                <tr>
                    <td>Address</td>
                    <td>
                        <textarea cols="17" rows="5" name="address"><?php echo (isset($array['address']) && !empty($array['address']))?$array['address']:''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <td>City</td>
                    <td>
                        <input type="text" name="city" value="<?php echo (isset($array['city']) && !empty($array['city']))?$array['city']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <td>State</td>
                    <td>
                        <input type="text" name="state" value="<?php echo (isset($array['state']) && !empty($array['state']))?$array['state']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <td>Postal Code</td>
                    <td>
                        <input type="text" name="zipcode" value="<?php echo (isset($array['zipcode']) && !empty($array['zipcode']))?$array['zipcode']:''; ?>" />
                    </td>
                </tr>
                <tr>
                    <td>Country</td>
                    <td>
                        <input type="text" name="country" value="<?php echo (isset($array['country']) && !empty($array['country']))?$array['country']:''; ?>" />
                    </td>
                </tr>
            </table>
            <input type="submit" value="Queue up the customer!" />
        </form>
    </body>
</html>