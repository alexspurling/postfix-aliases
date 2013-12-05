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
    return admin_url( "users.php?page=".$_GET['page'].'&alias='.$aliasname );
}

function pf_al_process_post() {

    global $wpdb, $pf_al_table_name;

    $redirect = false;

    //Add an address to an existing alias
    if(isset($_POST['newaddress'])) {
      $newaddress = $_POST['newaddress'];
      $aliasname = $_POST['aliasname'];
      $addresses = pf_al_get_addresses($aliasname);
      array_push($addresses, $newaddress);
      pf_al_store_addresses($aliasname, $addresses);
      $redirect = true;
    }

    //Add a new alias
    if(isset($_POST['newaliasname'])) {
      $newaliasname = $_POST['newaliasname'];
      $wpdb->insert(
        $pf_al_table_name,
        array(
         'address' => $newaliasname
        )
      );
      $redirect = true;
    }

    if ($redirect) {
      global $wp;
      $current_url = pf_al_get_main_page();

      error_log('About to redirect to: '.$current_url);
      wp_redirect($current_url);
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

function pf_al_display_all_aliases() {
    global $wpdb, $pf_al_table_name;
    $aliases = $wpdb->get_results("SELECT address FROM $pf_al_table_name");

    echo '<table><tr><th>Email address</th></tr>';
    foreach ( $aliases as $alias )
    {
        echo '<tr><td><a href="'.pf_al_get_alias_page($alias->address).'">'.$alias->address.'</td></tr>';
    }
    ?>
    </table>
    <hr>
    <form name="newaliasform" method="POST" action="">
      <h3>Add new mailing list</h3>
      <input name="newaliasname"><button type="submit">Add</button>
    </form>
    <?
}

function pf_al_get_addresses($aliasname)
{
    global $wpdb, $pf_al_table_name;
    $addressString = $wpdb->get_var($wpdb->prepare("SELECT goto FROM $pf_al_table_name WHERE address = '%s'", $aliasname));
    return explode(',',$addressString);
}

function pf_al_store_addresses($aliasname, $addresses)
{
    global $wpdb, $pf_al_table_name;
    $addressString = implode(',', $addresses);
    $wpdb->query($wpdb->prepare("UPDATE $pf_al_table_name SET goto = '$addressString' WHERE address = '$aliasname'"));
}

function pf_al_display_alias($aliasname) {
    $addresses = pf_al_get_addresses($aliasname);

    echo '<form name="aliasesform" method="POST" action="">';
    echo '<h3>'.$aliasname.'</h3>';
    echo '<select multiple="multiple" name="selectedaddresses" size="15" style="width: 300px;">';
    foreach ( $addresses as $address )
    {
        echo '<option>'.$address.'</option>';
    }
    echo '</select><br>';
    echo '<input type="hidden" name="aliasname" value="'.$aliasname.'">';
    echo '<input name="newaddress"><button type="submit">Add new address</button><br>';
    echo '<input type="submit" name="removeselected"  value="Remove all selected addresses"><br>';
    echo '</form>';
}
?>
