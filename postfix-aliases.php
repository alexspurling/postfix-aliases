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

/** Step 2 (from text above). */
add_action( 'admin_menu', 'my_plugin_menu' );

/** Step 1. */
function my_plugin_menu() {
    add_options_page( 'Postfix Alias Options', 'Postfix Aliases', 'manage_options', 'postfix-aliases', 'my_plugin_options' );
}

/** Step 3. */
function my_plugin_options() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    ?>
    <h2>Mailing Lists</h2>
    <?
    global $wpdb;
    $table_name = $wpdb->prefix . "pf_aliases";
    if(isset($_POST['aliasname'])) {

      $aliasname = $_POST['aliasname'];
      $addresses = $_POST['addresses'];
      $wpdb->insert(
        $table_name,
        array(
         'address' => $aliasname,
         'goto' => $addresses
        )
      );
      echo '<strong>Inserted alias for '.$aliasname.'</strong><br>';
    }

    $aliases = $wpdb->get_results(
        "
        SELECT address, goto
        FROM $table_name
        "
    );
    echo 'Found '.$aliases.length.' aliases';
    echo '<table>';
    foreach ( $aliases as $alias )
    {
        echo '<tr><td>'.$alias->address.'</td>';
        echo '<td>'.$alias->goto.'</td>';
        echo '<td><button type="button">Remove</button></td></tr>';
    }
    ?>
    </table>
    <form name="addalias" method="POST" action="">
      Alias: <input name="aliasname"><br>
      Addresses: <input name="addresses"><br>
      <button type="submit">Add new alias</button>
    </form>
    <?
}
?>