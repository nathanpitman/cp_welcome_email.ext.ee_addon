<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/*
=============================================================
	Control Panel Welcome Email
	- Nathan Pitman, www.ninefour.co.uk/labs
-------------------------------------------------------------
	Copyright (c) 2011 Nathan Pitman
=============================================================
	File:			ext.cp_welcome_email.php
=============================================================
	Version:		2.1
-------------------------------------------------------------
	Compatibility:	EE 2.x
-------------------------------------------------------------
	Purpose:		Automates the process of sending newly
					registered CP members a welcome email
					with username and password.				
=============================================================
*/

class Cp_welcome_email_ext {

	
	var $settings = array();
    
    var $addon_name = 'Control Panel Welcome Email';
    var $name = 'Control Panel Welcome Email';
    var $version = '2.0';
    var $description = 'Automates the process of sending newly registered CP members a welcome email with username and password.';
    var $settings_exist = 'y';
    var $docs_url = '';
	
	
	// ----------------------------------------
	//	Constructor - Extension use for settings
	// ----------------------------------------	
	
	
	function Cp_welcome_email_ext($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
	}
	
    public function __construct($settings = FALSE)
      {
        $this->EE =& get_instance();
        
        // define a constant for the current site_id rather than calling $PREFS->ini() all the time
        if (defined('SITE_ID') == FALSE)
            define('SITE_ID', $this->EE->config->item('site_id'));
        
        // set the settings for all other methods to access
        $this->settings = ($settings == FALSE) ? $this->_getSettings() : $this->_saveSettingsToSession($settings);
        
      }

	// ----------------------------------------
	//	welcome_email_send - Called by Extension Hook
	// ----------------------------------------	

	function cp_welcome_email_send($member_id) {
		
		$this->EE =& get_instance();

		$this->EE->load->library('email');
		$this->EE->load->helper('text');
		
		$password_string = "abcdefghijklmnopqrstuvwxyz1234567890";
	
		$password = "";
		for($i=0; $i<8; $i++) {
			$password .= $password_string[ rand(0, strlen($password_string)-1) ];
		}
	
		$message = $this->settings["welcome_email_body"];
		$from = $this->settings["welcome_email_from"];
		
		$subject = $this->settings["welcome_email_subject"];
		$subject = str_replace("{site_name}", $this->EE->config->item('site_name'), $subject);
		
		$query = $this->EE->db->query("SELECT username, screen_name, email from exp_members WHERE member_id = " . $member_id);
	
		$message = str_replace("{password}", $password, $message);
		$message = str_replace("{username}", $query->row("username"), $message);
		$message = str_replace("{name}", $query->row("screen_name"), $message);
		$message = str_replace("{site_name}", $this->EE->config->item('site_name'), $message);
		$message = str_replace("{site_url}", $this->EE->config->item('site_url'), $message);
	
		$this->EE->db->query("UPDATE exp_members SET password = SHA('". $password . "') WHERE member_id = " . $member_id);
		
		$to = $query->row("email");
	
		$this->EE->email->wordwrap = false;
		$this->EE->email->mailtype = 'text';	
		$this->EE->email->from( $from );
		$this->EE->email->to( $to );
		if ($this->settings["welcome_email_bcc"]=="yes") {
			$this->EE->email->bcc( $this->EE->config->item('webmaster_email') );
		}
		$this->EE->email->subject( $subject );
		$this->EE->email->message(entities_to_ascii( $message ));		
		$this->EE->email->Send();
	}

	// --------------------------------
	//  Activate Extension
	// --------------------------------
	
	function activate_extension()
	{
		$this->EE =& get_instance();
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'cp_welcome_email_send',
			'hook'		=> 'cp_members_member_create',
			'settings'	=> '',
			'priority'	=> 10,
			'version'	=> $this->version,
			'enabled'	=> 'n'
		);

		$this->EE->db->insert('extensions', $data);
	}
	
	function disable_extension()
	{
		$this->EE =& get_instance();
		
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}
	
	// --------------------------------
	//  Settings
	// --------------------------------  
	function settings()	{

		$this->EE =& get_instance();
	
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
	  	$settings['welcome_email_from']   = $this->EE->config->item('site_name').' <'.$this->EE->config->item('webmaster_email').'>';
	  	$settings['welcome_email_subject'] = 'Login Information for {site_name}';
	  	$settings['welcome_email_body']   = array('t', $body);
	  	$settings['welcome_email_bcc']   = array('r', array('yes' => "yes", 'no' => "no"), 'no');
		return($settings);	
	}
	
    /**
     * Saves the specified settings array to the database.
     *
     * @since Version 1.0.0
     * @access protected
     * @param array $settings an array of settings to save to the database.
     * @return void
     **/
    private function _getSettings($refresh = FALSE)
      {
        $settings = FALSE;
        if (isset($this->EE->session->cache[$this->addon_name][__CLASS__]['settings']) === FALSE || $refresh === TRUE)
          {
            $settings_query = $this->EE->db->select('settings')->where('enabled', 'y')->where('class', __CLASS__)->get('extensions', 1);
            
            if ($settings_query->num_rows())
              {
                $settings = unserialize($settings_query->row()->settings);
                $this->_saveSettingsToSession($settings);
              }
          }
        else
          {
            $settings = $this->EE->session->cache[$this->addon_name][__CLASS__]['settings'];
          }
        return $settings;
      }
    
    /**
     * Saves the specified settings array to the session.
     * @since Version 1.0.0
     * @access protected
     * @param array $settings an array of settings to save to the session.
     * @param array $sess A session object
     * @return array the provided settings array
     **/
    private function _saveSettingsToSession($settings, &$sess = FALSE)
      {
        // if there is no $sess passed and EE's session is not instaniated
        if ($sess == FALSE && isset($this->EE->session->cache) == FALSE)
            return $settings;
        
        // if there is an EE session available and there is no custom session object
        if ($sess == FALSE && isset($this->EE->session) == TRUE)
            $sess =& $this->EE->session;
        
        // Set the settings in the cache
        $sess->cache[$this->addon_name][__CLASS__]['settings'] = $settings;
        
        // return the settings
        return $settings;
      }
    
    
    /**
     * Saves the specified settings array to the database.
     *
     * @since Version 1.0.0
     * @access protected
     * @param array $settings an array of settings to save to the database.
     * @return void
     **/
    private function _saveSettingsToDB($settings)
      {
        $this->EE->db->where('class', __CLASS__)->update('extensions', array(
            'settings' => serialize($settings)
        ));
      }
	
}

/* End of file ext.cp_welcome_email.php */
/* Location: ./system/expressionengine/third_party/ext.cp_welcome_email.php */