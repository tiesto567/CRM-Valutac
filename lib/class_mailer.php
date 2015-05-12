<?php
  /**
   * Mailer Class
   *
   * @package Freelance Manager
   * @author wojoscripts.com
   * @copyright 2014
   * @version $Id: class_mailer.php, v1.00 2011-06-05 10:12:05 gewa Exp $
   */
  
  if (!defined("_VALID_PHP"))
      die('Direct access to this location is not allowed.');

  class Mailer
  {
	  
	  private static $instance;

      /**
       * Mailer::__construct()
       * 
       * @return
       */
      private function __construct(){}

      /**
       * Mailer::instance()
       * 
       * @return
       */
	  public static function instance(){
		  if (!self::$instance){ 
			  self::$instance = new Mailer(); 
		  } 
	  
		  return self::$instance;  
	  }

      /**
       * Mailer::sendMail()
       * 
       * @return
       */
      public static function sendMail()
      {
          require_once (BASEPATH . 'lib/swift/swift_required.php');
          
          if (Registry::get("Core")->mailer == "SMTP") {
			  $SSL = (Registry::get("Core")->is_ssl) ? 'ssl' : null;
              $transport = Swift_SmtpTransport::newInstance(Registry::get("Core")->smtp_host, Registry::get("Core")->smtp_port, $SSL)
						  ->setUsername(Registry::get("Core")->smtp_user)
						  ->setPassword(Registry::get("Core")->smtp_pass);
		  } elseif (Registry::get("Core")->mailer == "SMAIL") {
			  $transport = Swift_SendmailTransport::newInstance(Registry::get("Core")->sendmail);
          } else
              $transport = Swift_MailTransport::newInstance();
          
          return Swift_Mailer::newInstance($transport);
	  }
	  
  }
?>