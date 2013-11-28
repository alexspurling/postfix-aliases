<?php

global $wpdb;
$table_name = $wpdb->prefix . "pf_aliases";

function getAddresses($aliasname)
{
  $addressString = $wpdb->get_results("SELECT goto FROM $table_name WHERE address = $aliasname");
  return explode(',',$addressString);
}

function storeAddresses($aliasname, $addresses)
{
  $addressString = implode(',', $addresses);
  $wpdb->query("UPDATE $wpdb->$table_name SET goto = $addressString WHERE address = $aliasname");
}

//Add an address to an existing alias
if(isset($_POST['newaddress'])) {
  $newaddress = $_POST['newaddress'];
  $aliasname = $_POST['aliasname'];
  $addresses = getAddresses($aliasname);
  array_push($addresses, $newaddress);
  storeAddresses($aliasname, $addresses);
}

//Add a new alias
if(isset($_POST['newaliasname'])) {
  $newaliasname = $_POST['newaliasname'];
  $wpdb->insert(
    $table_name,
    array(
     'address' => $newaliasname
    )
  );
}

header('Location: postfix-aliases.php');
?>