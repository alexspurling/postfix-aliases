<?php
/**
 * Plugin Name: Postfix Aliases
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: Allows you to manage postfix email aliases.
 * Version: 1.0
 * Author: Alex
 * Author URI: http://URI_Of_The_Plugin_Author
 * License: GPL2
 */


global $pf_alias_db_version;
$pf_alias_db_version = "1.0";

$pf_al_table_name = $wpdb->prefix . "pf_aliases";

function pf_alias_install() {
    global $pf_alias_db_version;
    global $wpdb, $pf_al_table_name;

    $sql = "CREATE TABLE $pf_al_table_name (
      address varchar(255) NOT NULL default '',
      goto text NOT NULL,
      domain varchar(255) NOT NULL default '',
      created datetime NOT NULL default '0000-00-00 00:00:00',
      modified datetime NOT NULL default '0000-00-00 00:00:00',
      active tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (address),
      KEY address (address)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( "pf_alias_db_version", $pf_alias_db_version );
}

register_activation_hook(__FILE__, 'pf_alias_install');

//Register post processing
add_action('init', 'pf_al_process_post');
//Register the plugin's menu
add_action( 'admin_menu', 'pf_al_plugin_menu' );

//Add the plugin to the options menu
function pf_al_plugin_menu() {
    add_users_page('User Mailing Lists', 'Mailing Lists', 'edit_users', 'postfix-aliases', 'pf_al_plugin');
}

function pf_al_get_main_page() {
    return admin_url( "users.php?page=".$_GET['page'] );
}

function pf_al_get_alias_page($aliasname) {
    return admin_url( "users.php?page=".$_GET['page'].'&alias='.urlencode($aliasname));
}

function pf_al_process_post() {
    global $wpdb, $pf_al_table_name;
    global $wp;

    if(isset($_POST['removeaddress'])) {
      $aliasname = $_POST['aliasname'];
      pf_al_remove_from_alias($aliasname, $_POST['selectedaddresses']);
      wp_redirect(pf_al_get_alias_page($aliasname));
    }

    //Add an address to an existing alias
    if(isset($_POST['addnewaddress'])) {
      $newaddress = $_POST['newaddress'];
      $aliasname = $_POST['aliasname'];
      $addresses = pf_al_get_addresses($aliasname);
      array_push($addresses, $newaddress);
      pf_al_store_addresses($aliasname, $addresses);
      wp_redirect(pf_al_get_alias_page($aliasname));
    }

    //Add a new alias
    if(isset($_POST['addnewalias'])) {
      $newaliasname = $_POST['newaliasname'];
      $wpdb->insert(
        $pf_al_table_name,
        array(
         'address' => $newaliasname
        )
      );
      wp_redirect(pf_al_get_main_page());
    }
}

//This is called when showing the plugin's main page
function pf_al_plugin() {
    if ( !current_user_can( 'edit_users' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    ?>
    <h2>Mailing Lists</h2>
    <?

    if (!isset($_GET['alias'])) {
        pf_al_display_all_aliases();
    }else{
        pf_al_display_alias($_GET['alias']);
    }
}

function pf_al_get_all_aliases() {
    global $wpdb, $pf_al_table_name;
    $aliases = $wpdb->get_results("SELECT address, goto FROM $pf_al_table_name");
    foreach ($aliases as $alias) {
        $alias->addresses = pf_al_get_address_array($alias->goto);
        $alias->numAddresses = count($alias->addresses);
    }
    return $aliases;
}

function pf_al_display_all_aliases() {
    $aliases = pf_al_get_all_aliases();

    echo '<table><tr><th>Mailing List</th><th>Num Subscribers</th></tr>';
    foreach ($aliases as $alias) {
        echo '<tr><td>'.pf_al_get_alias_page_link($alias->address).'</td><td>'.$alias->numAddresses.'</td></tr>';
    }
    ?>
    </table>
    <hr>
    <form name="newaliasform" method="POST" action="">
      <h3>Add new mailing list</h3>
      <input name="newaliasname"><input type="submit" name="addnewalias" value="Add">
    </form>
    <hr>
    <form name="searchform" method="GET" action="">
      <h3>Search for email address</h3>
      <input name="page" type="hidden" value="<?=$_GET['page']?>">
      <input name="searchaddress"><input type="submit" name="search" value="Search">
    </form>
    <?
    if(isset($_GET['searchaddress'])) {
      $searchaddress = $_GET['searchaddress'];
      $searchresults = pf_al_get_search_results($aliases, $searchaddress);
      echo '<table><tr><th>Email</th><th>Subscribed to</th></tr>';
      foreach ($searchresults as $address => $subscribedto) {
        echo "<tr><td>$address</td><td>";
        //Loop through all the mailing lists this email appears in and print a comma separated link for it
        $i = 0;
        foreach ($subscribedto as $sub) {
          echo pf_al_get_alias_page_link($sub);
          if (++$i != count($subscribedto)) {
            echo ", ";
          }
        }
        echo "</td></tr>";
      }
      echo "</table>";
    }
}

function pf_al_get_alias_page_link($alias) {
    return '<a href="'.pf_al_get_alias_page($alias).'">'.$alias.'</a>';
}

function pf_al_get_search_results($aliases, $searchaddress) {
    $results = array();
    foreach ($aliases as $alias) {
      foreach ($alias->addresses as $address) {
        if (strpos($address, $searchaddress) !== false) {
          if (!array_key_exists($address, $results)) {
            $results[$address] = array();
          }
          array_push($results[$address], $alias->address);
        }
      }
    }
    return $results;
}

function pf_al_get_address_array($address_string) {
    if (strlen($address_string) == 0) {
        return array();
    }
    return explode(',',$address_string);
}

function pf_al_get_addresses($aliasname)
{
    global $wpdb, $pf_al_table_name;
    $addressString = $wpdb->get_var($wpdb->prepare("SELECT goto FROM $pf_al_table_name WHERE address = '%s'", $aliasname));
    return pf_al_get_address_array($addressString);
}

function pf_al_store_addresses($aliasname, $addresses)
{
    global $wpdb, $pf_al_table_name;
    $addressString = implode(',', $addresses);
    $wpdb->query($wpdb->prepare("UPDATE $pf_al_table_name SET goto = '%s' WHERE address = '%s'", $addressString, $aliasname));
}

function pf_al_remove_from_alias($aliasname, $addresses_to_remove)
{
    $addresses = pf_al_get_addresses($aliasname);
    $newAddresses = array_diff($addresses, $addresses_to_remove);
    pf_al_store_addresses($aliasname, $newAddresses);
}

function pf_al_display_alias($aliasname) {
    $addresses = pf_al_get_addresses($aliasname);

    echo '<a href="'.pf_al_get_main_page().'">Back</a>';
    echo '<form name="aliasesform" method="POST" action="">';
    echo '<h3>'.$aliasname.'</h3>';
    echo '<table><tr>';
    echo '<td><select multiple="multiple" name="selectedaddresses[]" size="15" style="width: 300px;">';
    foreach ( $addresses as $address )
    {
        echo '<option>'.$address.'</option>';
    }
    echo '</select></td>';
    echo '<td valign="top"><input type="submit" name="removeaddress" value="Remove"></td>';
    echo '</tr></table>';
    echo '<input type="hidden" name="aliasname" value="'.$aliasname.'">';
    echo '<input name="newaddress"><input type="submit" name="addnewaddress" value="Add new address"><br>';
    echo '</form>';
}

?>
