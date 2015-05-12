<?php
  /**
   * Stripe IPN
   *
   * @package Freelance Manager
   * @author wojoscripts.com
   * @copyright 2013
   * @version $Id: ipn.php, v2.00 2013-05-08 10:12:05 gewa Exp $
   */
  define("_VALID_PHP", true);

  require_once ("../../init.php");
  require_once (dirname(__file__) . '/lib/Stripe.php');

  ini_set('log_errors', true);
  ini_set('error_log', dirname(__file__) . '/ipn_errors.log');

  if (!$user->logged_in)
      redirect_to("../../index.php");
	  
  if (isset($_POST['processStripePayment'])) {
      $key = $db->first("SELECT * FROM gateways WHERE name = 'stripe'");
      $stripe = array("secret_key" => $key->extra, "publishable_key" => $key->extra3);
      Stripe::setApiKey($stripe['secret_key']);

      try {
          $charge = Stripe_Charge::create(array(
              "amount" => round($_POST['amount'] * 100, 0), // amount in cents, again
              "currency" => $_POST['currency_code'],
              "card" => array(
                  "number" => $_POST['card-number'],
                  "exp_month" => $_POST['card-expiry-month'],
                  "exp_year" => $_POST['card-expiry-year'],
                  "cvc" => $_POST['card-cvc'],
                  ),
              "description" => $_POST['item_name']));
          $json = json_decode($charge);
          $amount_charged = round(($json->{'amount'} / 100), 2);
          
		  /* == Payment Completed == */
          $inv_id = $_POST['item_number'];
		  $invrow = $db->first("SELECT * FROM invoices WHERE id = " . (int)$inv_id);
		  
		  if ($invrow) {
			  $edata = array(
				  'project_id' => $invrow->project_id,
				  'invoice_id' => $invrow->id,
				  'amount' => floatval($amount_charged),
				  'recurring' => 0,
				  'created' => "NOW()",
				  'method' => "Stripe",
				  'description' => "Payment via Stripe");
  
			  $db->insert("invoice_payments", $edata);
  
			  $row = $db->first("SELECT SUM(amount) as amtotal FROM invoice_payments WHERE invoice_id = {$invrow->id} GROUP BY invoice_id");
  
			  $db->update("invoices", array('amount_paid' => $row->amtotal), "id=" . $invrow->id);
			  $db->update(Content::pTable, array('b_status' => $row->amtotal), "id=" . $invrow->project_id);
  
			  $row2 = $db->first("SELECT amount_total, amount_paid FROM invoices WHERE id = " . $invrow->id);
			  $idata['status'] = ($row2->amount_total == $row2->amount_paid) ? 'Paid' : 'Unpaid';
			  $db->update("invoices", $idata, "id=" . $invrow->id);

			  $jn['type'] = 'success';
			  $jn['message'] = 'Thank you. Payment completed.';
			  print json_encode($jn);
		  
			  /* == Notify User == */
			  require_once (BASEPATH . "lib/class_mailer.php");
			  $mailer = Mailer::sendMail();
  
			  $userdata = $db->first("SELECT i.*," 
			  . "\n p.title as ptitle, CONCAT(u.fname,' ',u.lname) as fullname, u.username, u.email, u.address, u.city, u.zip, u.state, u.phone, u.company" 
			  . "\n FROM invoices as i" 
			  . "\n LEFT JOIN " . Content::pTable . " as p ON p.id = i.project_id" 
			  . "\n LEFT JOIN " . Users::uTable . " as u ON u.id = i.client_id" 
			  . "\n WHERE i.id = " . $edata['invoice_id']);

			  ob_start();
			  require_once (BASEPATH . 'mailer/Email_Payment.tpl.php');
			  $html_message = ob_get_contents();
			  ob_end_clean();

			  if (file_exists(UPLOADS . 'print_logo.png')) {
				  $logo = '<img src="' . UPLOADURL . 'print_logo.png" alt="' . Registry::get("Core")->company . '" />';
			  } elseif (Registry::get("Core")->logo) {
				  $logo = '<img src="' . UPLOADURL . Registry::get("Core")->logo . '" alt="' . Registry::get("Core")->company . '" />';
			  } else {
				  $logo = Registry::get("Core")->company;
			  }
			  
			  $body = str_replace(array(
				  '[LOGO]',
				  '[UNAME]',
				  '[PTITLE]',
				  '[INVID]',
				  '[TITLE]',
				  '[GROSS]',
				  '[TOTAL]',
				  '[DATE]',
				  '[METHOD]',
				  '[CCOMPANY]',
				  '[SITEURL]'), array(
				  $logo,
				  Core::renderName($userdata),
				  $userdata->ptitle,
				  Registry::get("Core")->invoice_number . $inv_id,
				  $userdata->title,
				  $mc_gross,
				  $userdata->amount_total - $userdata->amount_paid,
				  date('Y-m-d'),
				  $edata['method'],
				  Registry::get("Core")->company,
				  SITEURL), $html_message);
				  
              $subject = Lang::$word->STAFF_PAYCOMPLETE_OK . $userdata->ptitle;
			  
			  $msg = Swift_Message::newInstance()
						->setSubject($subject)
						->setTo(array($userdata->email => Core::renderName($userdata)))
						->setFrom(array(Registry::get("Core")->site_email => Registry::get("Core")->company))
						->setBody($body, 'text/html');
			  $mailer->send($msg);

		  }
      }
      catch (Stripe_CardError $e) {
          //$json = json_decode($e);
          $body = $e->getJsonBody();
          $err = $body['error'];
          $json['type'] = 'error';
          Filter::$msgs['status'] = 'Status is:' . $e->getHttpStatus() . "\n";
          Filter::$msgs['type'] = 'Type is:' . $err['type'] . "\n";
          Filter::$msgs['code'] = 'Code is:' . $err['code'] . "\n";
          Filter::$msgs['param'] = 'Param is:' . $err['param'] . "\n";
          Filter::$msgs['msg'] = 'Message is:' . $err['message'] . "\n";
          $json['message'] = Filter::msgStatus();
          print json_encode($json);
      }
      catch (Stripe_InvalidRequestError $e) {}
      catch (Stripe_AuthenticationError $e) {}
      catch (Stripe_ApiConnectionError $e) {}
      catch (Stripe_Error $e) {}
      catch (exception $e) {}
  }
?>