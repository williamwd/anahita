<?php
/**
 * @version		$Id: application.php 15097 2010-02-27 14:19:54Z ian $
 * @package		Joomla
 * @subpackage	Config
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once( JPATH_COMPONENT.DS.'views'.DS.'application'.DS.'view.php' );

/**
 * @package		Joomla
 * @subpackage	Config
 */
class ConfigControllerApplication extends ConfigController
{
	/**
	 * Custom Constructor
	 */
	function __construct( $default = array() )
	{
		$default['default_task'] = 'showConfig';
		parent::__construct( $default );

		$this->registerTask( 'apply', 'save' );
	}

	/**
	 * Show the configuration edit form
	 * @param string The URL option
	 */
	public static function showConfig()
	{
		// Initialize some variables
		$db =& JFactory::getDBO();
		$row = new JConfig();

		// compile list of the languages
		$langs 		= array ();
		$menuitems 	= array ();
		$lists 		= array ();

		// PRE-PROCESS SOME LIST

		// -- Show/Hide --

		$show_hide		= array (JHTML::_('select.option', 1, JText::_('Hide')), JHTML::_('select.option', 0, JText::_('Show')));
		$show_hide_r 	= array (JHTML::_('select.option', 0, JText::_('Hide')), JHTML::_('select.option', 1, JText::_('Show')));

		// DEBUG
		$lists['debug'] 		= JHTML::_('select.booleanlist', 'debug', 'class="inputbox"', $row->debug);
		//$lists['debug_lang'] 	= JHTML::_('select.booleanlist', 'debug_lang', 'class="inputbox"', $row->debug_lang);

		// DATABASE SETTINGS

		// SERVER SETTINGS
		$errors 				= array (JHTML::_('select.option', -1, JText::_('System Default')), JHTML::_('select.option', 0, JText::_('None')), JHTML::_('select.option', E_ERROR | E_WARNING | E_PARSE, JText::_('Simple')), JHTML::_('select.option', E_ALL ^ E_STRICT, JText::_('Maximum')));

		$lists['error_reporting'] = JHTML::_('select.genericlist',  $errors, 'error_reporting', 'class="inputbox" size="1"', 'value', 'text', $row->error_reporting);
		//$lists['enable_ftp'] 	= JHTML::_('select.booleanlist', 'ftp_enable', 'class="inputbox"', intval($row->ftp_enable));


		// MAIL SETTINGS
		$mailer = array (
			JHTML::_('select.option', 'mail', JText::_('PHP mail function')),
			JHTML::_('select.option', 'sendmail', JText::_('Sendmail')),
			JHTML::_('select.option', 'smtp', JText::_('SMTP Server')));
		$lists['mailer'] = JHTML::_('select.genericlist',  $mailer, 'mailer', 'class="inputbox" size="1"', 'value', 'text', $row->mailer);
		$smtpsecure = array (
			JHTML::_('select.option', 'none', JText::_('None')),
			JHTML::_('select.option', 'ssl', 'SSL'),
			JHTML::_('select.option', 'tls', 'TLS'));
		$lists['smtpsecure'] = JHTML::_('select.genericlist',  $smtpsecure, 'smtpsecure', 'class="inputbox" size="1"', 'value', 'text', (isset($row->smtpsecure) ? $row->smtpsecure : ''));
		$lists['smtpauth'] = JHTML::_('select.booleanlist', 'smtpauth', 'class="inputbox"', $row->smtpauth);

		// CACHE SETTINGS
		$lists['caching'] = JHTML::_('select.booleanlist', 'caching', 'class="inputbox"', $row->caching);
		jimport('joomla.cache.cache');
		$stores = JCache::getStores();
		$options = array();
		foreach($stores as $store) {
			$options[] = JHTML::_('select.option', $store, JText::_(ucfirst($store)) );
		}
		$lists['cache_handlers'] = JHTML::_('select.genericlist',  $options, 'cache_handler', 'class="inputbox" size="1"', 'value', 'text', $row->cache_handler);

		// MEMCACHE SETTINGS
		if (!empty($row->memcache_settings) && !is_array($row->memcache_settings)) {
			$row->memcache_settings = unserialize(stripslashes($row->memcache_settings));
		}
		$lists['memcache_persist'] = JHTML::_('select.booleanlist', 'memcache_settings[persistent]', 'class="inputbox"', @$row->memcache_settings['persistent']);
		$lists['memcache_compress'] = JHTML::_('select.booleanlist', 'memcache_settings[compression]', 'class="inputbox"', @$row->memcache_settings['compression']);

		// SEO SETTINGS
		//$lists['sef'] 		= JHTML::_('select.booleanlist', 'sef', 'class="inputbox"', $row->sef);
		$lists['sef_rewrite'] 	= JHTML::_('select.booleanlist', 'sef_rewrite', 'class="inputbox"', $row->sef_rewrite);
		//$lists['sef_suffix'] 	= JHTML::_('select.booleanlist', 'sef_suffix', 'class="inputbox"', $row->sef_suffix);

		// SESSION SETTINGS
		$stores = JSession::getStores();
		$options = array();
		foreach($stores as $store) {
			$options[] = JHTML::_('select.option', $store, JText::_(ucfirst($store)) );
		}
		$lists['session_handlers'] = JHTML::_('select.genericlist',  $options, 'session_handler', 'class="inputbox" size="1"', 'value', 'text', $row->session_handler);

		// SHOW EDIT FORM
		ConfigApplicationView::showConfig($row, $lists);
	}

	/**
	 * Save the configuration
	 */
	function save()
	{
		global $mainframe;

		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
		$ftp = JClientHelper::getCredentials('ftp');

		//Save user and media manager settings
		$table =& JTable::getInstance('component');

		$config = new JRegistry('config');
		$config_array = array();

		// SITE SETTINGS
		$config_array['helpurl']	= JRequest::getVar('helpurl', 'http://www.GetAnahita.com', 'post', 'string');

		// DEBUG
		$config_array['debug']		= JRequest::getVar('debug', 0, 'post', 'int');
		$config_array['debug_lang']	= JRequest::getVar('debug_lang', 0, 'post', 'int');

		// SEO SETTINGS
		$config_array['sef']			= JRequest::getVar('sef', 0, 'post', 'int');
		$config_array['sef_rewrite']	= JRequest::getVar('sef_rewrite', 0, 'post', 'int');
		$config_array['sef_suffix']		= JRequest::getVar('sef_suffix', 0, 'post', 'int');

		// SERVER SETTINGS
		$config_array['secret']				= JRequest::getVar('secret', 0, 'post', 'string');
		$config_array['error_reporting']	= JRequest::getVar('error_reporting', -1, 'post', 'int');
		$config_array['log_path']			= JRequest::getVar('log_path', JPATH_ROOT.DS.'logs', 'post', 'string');
		$config_array['tmp_path']			= JRequest::getVar('tmp_path', JPATH_ROOT.DS.'tmp', 'post', 'string');
		$config_array['live_site'] 			= preg_replace('/\/administrator.*/','',JURI::getInstance()->toString(array('scheme','host','port','path')));//rtrim(JRequest::getVar('live_site','','post','string'), );

		// LOCALE SETTINGS
		$config_array['offset']				= JRequest::getVar('offset', 0, 'post', 'float');

		// CACHE SETTINGS
		$config_array['caching']			= JRequest::getVar('caching', 0, 'post', 'int');
		$config_array['cachetime']			= JRequest::getVar('cachetime', 900, 'post', 'int');
		$config_array['cache_handler']		= JRequest::getVar('cache_handler', 'file', 'post', 'word');
		$config_array['memcache_settings']	= JRequest::getVar('memcache_settings', array(), 'post');

		// FTP SETTINGS
		$config_array['ftp_enable']	= JRequest::getVar('ftp_enable', 0, 'post', 'int');
		$config_array['ftp_host']	= JRequest::getVar('ftp_host', '', 'post', 'string');
		$config_array['ftp_port']	= JRequest::getVar('ftp_port', '', 'post', 'int');
		$config_array['ftp_user']	= JRequest::getVar('ftp_user', '', 'post', 'string');
		$config_array['ftp_pass']	= JRequest::getVar('ftp_pass', '', 'post', 'string', JREQUEST_ALLOWRAW);
		$config_array['ftp_root']	= JRequest::getVar('ftp_root', '', 'post', 'string');

		// DATABASE SETTINGS
		$config_array['dbtype']		= JRequest::getVar('dbtype', 'mysql', 'post', 'word');
		$config_array['host']		= JRequest::getVar('host', 'localhost', 'post', 'string');
		$config_array['user']		= JRequest::getVar('user', '', 'post', 'string');
		$config_array['db']		= JRequest::getVar('db', '', 'post', 'string');
		$config_array['dbprefix']	= JRequest::getVar('dbprefix', 'jos_', 'post', 'string');

		// MAIL SETTINGS
		$config_array['mailer']		= JRequest::getVar('mailer', 'mail', 'post', 'word');
		$config_array['mailfrom']	= JRequest::getVar('mailfrom', '', 'post', 'string');
		$config_array['fromname']	= JRequest::getVar('fromname', 'Joomla 1.5', 'post', 'string');
		$config_array['sendmail']	= JRequest::getVar('sendmail', '/usr/sbin/sendmail', 'post', 'string');
		$config_array['smtpauth']	= JRequest::getVar('smtpauth', 0, 'post', 'int');
		$config_array['smtpsecure']	= JRequest::getVar('smtpsecure', 'none', 'post', 'word');
		$smtpport	        	= JRequest::getVar('smtpport', '', 'post', 'int');
		$config_array['smtpport']	= $smtpport ? $smtpport : '25';
		$config_array['smtpuser']	= JRequest::getVar('smtpuser', '', 'post', 'string');
		$config_array['smtppass']	= JRequest::getVar('smtppass', '', 'post', 'string', JREQUEST_ALLOWRAW);
		$config_array['smtphost']	= JRequest::getVar('smtphost', '', 'post', 'string');

		// META SETTINGS
		$config_array['MetaAuthor']	= JRequest::getVar('MetaAuthor', 1, 'post', 'int');
		$config_array['MetaTitle']	= JRequest::getVar('MetaTitle', 1, 'post', 'int');

		// SESSION SETTINGS
		$config_array['lifetime']			= JRequest::getVar('lifetime', 0, 'post', 'int');
		$config_array['session_handler']	= JRequest::getVar('session_handler', 'none', 'post', 'word');

		//LANGUAGE SETTINGS
		//$config_array['lang']				= JRequest::getVar('lang', 'none', 'english', 'cmd');
		//$config_array['language']			= JRequest::getVar('language', 'en-GB', 'post', 'cmd');

		$config->loadArray($config_array);

		//override any possible database password change
		$config->setValue('config.password', $mainframe->getCfg('password'));

		// handling of special characters
		$sitename			= htmlspecialchars( JRequest::getVar( 'sitename', '', 'post', 'string' ), ENT_COMPAT, 'UTF-8' );
		$config->setValue('config.sitename', $sitename);

		$MetaDesc			= htmlspecialchars( JRequest::getVar( 'MetaDesc', '', 'post', 'string' ),  ENT_COMPAT, 'UTF-8' );
		$config->setValue('config.MetaDesc', $MetaDesc);

		$MetaKeys			= htmlspecialchars( JRequest::getVar( 'MetaKeys', '', 'post', 'string' ),  ENT_COMPAT, 'UTF-8' );
		$config->setValue('config.MetaKeys', $MetaKeys);

		// handling of quotes (double and single) and amp characters
		// htmlspecialchars not used to preserve ability to insert other html characters
		$offline_message	= JRequest::getVar( 'offline_message', '', 'post', 'string' );
		$offline_message	= JFilterOutput::ampReplace( $offline_message );
		$offline_message	= str_replace( '"', '&quot;', $offline_message );
		$offline_message	= str_replace( "'", '&#039;', $offline_message );
		$config->setValue('config.offline_message', $offline_message);

		//purge the database session table (only if we are changing to a db session store)
		if($mainframe->getCfg('session_handler') != 'database' && $config->getValue('session_handler') == 'database')
		{
			$table =& JTable::getInstance('session');
			$table->purge(-1);
		}

		// Get the path of the configuration file
		$fname = JPATH_CONFIGURATION.DS.'configuration.php';

		// Update the credentials with the new settings
		$oldconfig =& JFactory::getConfig();
		$oldconfig->setValue('config.ftp_enable', $config_array['ftp_enable']);
		$oldconfig->setValue('config.ftp_host', $config_array['ftp_host']);
		$oldconfig->setValue('config.ftp_port', $config_array['ftp_port']);
		$oldconfig->setValue('config.ftp_user', $config_array['ftp_user']);
		$oldconfig->setValue('config.ftp_pass', $config_array['ftp_pass']);
		$oldconfig->setValue('config.ftp_root', $config_array['ftp_root']);
		JClientHelper::getCredentials('ftp', true);

		if(!$config->get('caching') && $oldconfig->get('caching')) {
			$cache = JFactory::getCache();
			$cache->clean();
		}

		// Try to make configuration.php writeable
		jimport('joomla.filesystem.path');
		if (!$ftp['enabled'] && JPath::isOwner($fname) && !JPath::setPermissions($fname, '0644')) {
			JError::raiseNotice('SOME_ERROR_CODE', 'Could not make configuration.php writable');
		}

		// Get the config registry in PHP class format and write it to configuation.php
		jimport('joomla.filesystem.file');
		if (JFile::write($fname, $config->toString('PHP', 'config', array('class' => 'JConfig')))) {
			$msg = JText::_('The Configuration Details have been updated');
		} else {
			$msg = JText::_('ERRORCONFIGFILE');
		}

		// Redirect appropriately
		$task = $this->getTask();
		switch ($task) {
			case 'apply' :
				$this->setRedirect('index.php?option=com_config', $msg);
				break;

			case 'save' :
			default :
				$this->setRedirect('index.php', $msg);
				break;
		}

		// Try to make configuration.php unwriteable
		//if (!$ftp['enabled'] && JPath::isOwner($fname) && !JPath::setPermissions($fname, '0444')) {
		if ($config_array['ftp_enable']==0 && !$ftp['enabled'] && JPath::isOwner($fname) && !JPath::setPermissions($fname, '0444')) {
			JError::raiseNotice('SOME_ERROR_CODE', 'Could not make configuration.php unwritable');
		}
	}

	/**
	 * Cancel operation
	 */
	function cancel()
	{
		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		$this->setRedirect( 'index.php' );
	}
}
