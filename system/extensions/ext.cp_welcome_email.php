<?php

if ( ! defined('EXT') )
{
	exit('Invalid file request');
}

/*
=============================================================
	Control Panel Welcome Email
	- Nathan Pitman, www.ninefour.co.uk/labs
-------------------------------------------------------------
	Copyright (c) 2009 Nathan Pitman
=============================================================
	File:			ext.welcome_email.php
=============================================================
	Version:		1.0.0
-------------------------------------------------------------
	Compatibility:	EE 1.6.x
-------------------------------------------------------------
	Purpose:		Automates the process of sending newly
					registered CP members a welcome email
					with username and password.				
=============================================================
*/

class Cp_welcome_email
{
	var $settings		=	array();
	var $name			=	'Control Panel Welcome Email';
	var $version		=	'1.0.0';
	var $description	=	'Automates the process of sending newly registered CP members a welcome email with username and password.';
	var $settings_exist	=	'y';
	var $docs_url		=	'http://ninefour.co.uk/labs';
	
	// ----------------------------------------
	//	Constructor - Extension use for settings
	// ----------------------------------------	
	
	function Cp_welcome_email($settings='')
	{
		$this->settings = $settings;
	}

	// ----------------------------------------
	//	welcome_email_send - Called by Extension Hook
	// ----------------------------------------	

	function cp_welcome_email_send($member_id) {
		global $PREFS, $DB, $REGX;
	
		if ( ! class_exists('EEmail'))
		{
			require PATH_CORE.'core.email'.EXT;
		}
	
		$password_string = "abcdefghijklmnopqrstuvwxyz1234567890";
	
		$password = "";
		for($i=0; $i<8; $i++) {
			$password .= $password_string[ rand(0, strlen($password_string)-1) ];
		}
	
		$message = $this->settings["welcome_email_body"];
		$from = $this->settings["welcome_email_from"];
		
		$subject = $this->settings["welcome_email_subject"];
		$subject = str_replace("{site_name}", $PREFS->ini('site_name'), $subject);
		
		$query = $DB->query("SELECT username, screen_name, email from exp_members WHERE member_id = " . $member_id);
	
		$message = str_replace("{password}", $password, $message);
		$message = str_replace("{username}", $query->row["username"], $message);
		$message = str_replace("{name}", $query->row["screen_name"], $message);
		$message = str_replace("{site_name}", $PREFS->ini('site_name'), $message);
		$message = str_replace("{site_url}", $PREFS->ini('site_url'), $message);
	
		$DB->query("UPDATE exp_members SET password = SHA('". $password . "') WHERE member_id = " . $member_id);
		
		$to = $query->row["email"];
	
		$email = new EEmail;
		$email->wordwrap = false;
		$email->mailtype = 'text';	
		$email->from( $from );
		$email->to( $to );
		if ($this->settings["welcome_email_bcc"]=="yes") {
			$email->bcc( $PREFS->ini('webmaster_email') );
		}
		$email->subject( $subject );
		$email->message($REGX->entities_to_ascii( $message ));		
		$email->Send();
	}

	// --------------------------------
	//  Activate Extension
	// --------------------------------
	
	function activate_extension() {
		global $DB, $PREFS;
	
		$sql[] = $DB->insert_string( 'exp_extensions', 
			array(					
				'extension_id' 	=> '',
				'class'			=> get_class($this),
				'method'		=> "cp_welcome_email_send",
				'hook'			=> "cp_members_member_create",
				'settings'		=> '',
				'priority'		=> 10,
				'version'		=> $this->version,
				'enabled'		=> "n"
			)
		);
		
		foreach ($sql as $query) {
			$DB->query($query);
		}
		return TRUE;
	}
	// END
  
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	function update_extension($current='') {
		global $DB;
		
		if ($current == '' OR $current == $this->version) {
			return FALSE;
		}
		if ($current > '1.0.0') {
			// Update queries for next version 1.0.1
		}
		$DB->query("UPDATE exp_extensions 
					SET version = '".$DB->escape_str($this->version)."' 
					WHERE class = '".get_class($this)."'");
	}
	// END
	
	// --------------------------------
	//  Settings
	// --------------------------------  
	function settings()	{
		global $PREFS;
	
		// Complex:
		// [variable_name] => array(type, values, default)
		// variable_name => short name for setting and used as the key for language file
		// type:  t - textarea, r - radio buttons, s - select, ms - multiselect, f - function calls
		// values:  can be array (r, s, ms), string (t), function name (f)
		// default:  name of array member, string, nothing
		//
		// Simple:
		// [variable_name] => 'Butter'
		// Text input, with 'Butter' as the default.
		
		$body = "{name}\r\n";
		$body .= "\r\n";
		$body .= "Here is your login information for {site_name}:\r\n";
		$body .= "\r\n";
		$body .= "	Username: {username}\r\n";
		$body .= "	Password: {password}\r\n";
		$body .= "\r\n";
		$body .= "{site_name}\r\n";
		$body .= "{site_url}";
	
		$radio_bcc = array();
		$radio_bcc[] = "Yes";
		$radio_bcc[] = "No";
	
		$settings = array();
	  	$settings['welcome_email_from']   = $PREFS->ini('site_name').' <'.$PREFS->ini('webmaster_email').'>';
	  	$settings['welcome_email_subject'] = 'Login Information for {site_name}';
	  	$settings['welcome_email_body']   = array('t', $body);
	  	$settings['welcome_email_bcc']   = array('r', array('yes' => "yes", 'no' => "no"), 'no');
		return($settings);	
	}
	// END	

}
// END Class
?>