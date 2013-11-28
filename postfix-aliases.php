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

function pf_alias_install() {
   global $wpdb;
   global $pf_alias_db_version;

   $table_name = $wpdb->prefix . "pf_aliases";

   $sql = "CREATE TABLE $table_name (
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

function pf_alias_install_data() {
   global $wpdb;
   $welcome_name = "Mr. WordPress";
   $welcome_text = "Congratulations, you just completed the installation!";

   $rows_affected = $wpdb->insert( $table_name, array( 'time' => current_time('mysql'), 'name' => $welcome_name, 'text' => $welcome_text ) );
}

register_activation_hook(__FILE__, 'pf_alias_install');

//Register post processing
add_action('init', 'process_post');
//Register the plugin's menu
add_action( 'admin_menu', 'pf_al_plugin_menu' );

//Add the plugin to the options menu
function pf_al_plugin_menu() {
    add_options_page( 'Postfix Alias Options', 'Postfix Aliases', 'manage_options', 'postfix-aliases', 'pf_al_plugin' );
}

function process_post() {

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
        $table_name,
        array(
         'address' => $newaliasname
        )
      );
      $redirect = true;
    }

    if ($redirect) {
      global $wp;
      $current_url = admin_url( "options-general.php?page=".$_GET["page"] );

      error_log('About to redirect to: '.$current_url);
      wp_redirect($current_url);
    }
}

function pf_al_get_addresses($aliasname)
{
  global $wpdb;
  $table_name = $wpdb->prefix . "pf_aliases";
  $addressString = $wpdb->get_var("SELECT goto FROM $table_name WHERE address = '$aliasname'");
  return explode(',',$addressString);
}

function pf_al_store_addresses($aliasname, $addresses)
{
  global $wpdb;
  $table_name = $wpdb->prefix . "pf_aliases";
  $addressString = implode(',', $addresses);
  $wpdb->query("UPDATE $table_name SET goto = '$addressString' WHERE address = '$aliasname'");
}

//This is called when showing the plugin's option page
function pf_al_plugin() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    ?>
    <h2>Mailing Lists</h2>
    <?
    global $wpdb;
    $table_name = $wpdb->prefix . "pf_aliases";

    $aliases = $wpdb->get_results("SELECT address, goto FROM $table_name");
    foreach ( $aliases as $alias )
    {
      echo '<form name="aliasesform" method="POST" action="">';
      echo '<h3>'.$alias->address.'</h3>';
      echo '<select multiple="multiple" name="selectedaddresses" size="15" style="width: 300px;">';
      $addresses = explode(',',$alias->goto);
      foreach ( $addresses as $address )
      {
        echo '<option>'.$address.'</option>';
      }
      echo '</select><br>';
      echo '<input type="hidden" name="aliasname" value="'.$alias->address.'">';
      ?>
      <input name="newaddress"><button type="submit">Add new address</button><br>
      <input type="submit" name="removeselected"  value="Remove all selected addresses"><br>
      </form>
      <?
    }
    ?>
    <hr>
    <form name="newaliasform" method="POST" action="">
      <h3>Add new alias</h3>
      <input name="newaliasname"><button type="submit">Add</button>
    </form>
    <?
}
?>
