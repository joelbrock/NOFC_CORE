<?php
/*******************************************************************************

    Copyright 2007,2010 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

if (!class_exists("BasicCCModule")) include_once(realpath(dirname(__FILE__)."/BasicCCModule.php"));
if (!class_exists("xmlData")) include_once(realpath(dirname(__FILE__)."/lib/xmlData.php"));
if (!class_exists("PaycardLib")) include_once(realpath(dirname(__FILE__)."/lib/paycardLib.php"));

if (!isset($CORE_LOCAL)){
	include(realpath(dirname(__FILE__)."/lib/LS_Access.php"));
	$CORE_LOCAL = new LS_Access();
}

if (!class_exists("AutoLoader")) include_once(realpath(dirname(__FILE__).'/../lib/AutoLoader.php'));

define('GOEMERCH_ID','');
define('GOEMERCH_PASSWD','');
define('GOEMERCH_GATEWAY_ID','');
// True - settle transactions immediately
// False - just get authorizations. MUST SETTLE MANUALLY LATER!
define('GOEMERCH_SETTLE_IMMEDIATE',True);

/* test credentials 
define('GOEMERCH_ID','1264');
define('GOEMERCH_PASSWD','password');
define('GOEMERCH_GATEWAY_ID','a91c38c3-7d7f-4d29-acc7-927b4dca0dbe');
*/

class GoEMerchant extends BasicCCModule {

	var $voidTrans;
	var $voidRef;

	function handlesType($type){
		if ($type == PaycardLib::PAYCARD_TYPE_CREDIT) return True;
		else return False;
	}

	function entered($validate,$json){
		global $CORE_LOCAL;
		// error checks based on card type
		if( $CORE_LOCAL->get("CCintegrate") != 1) { // credit card integration must be enabled
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_GIFT,
				"Card Integration Disabled",
				"Please process credit cards in standalone",
				"[clear] to cancel");
			return $json;
		}

		// error checks based on processing mode
		switch( $CORE_LOCAL->get("paycard_mode")) {
		case PaycardLib::PAYCARD_MODE_VOID:
			// use the card number to find the trans_id
			$dbTrans = PaycardLib::paycard_db();
			$today = date('Ymd');
			$pan4 = substr($this->$trans_pan['pan'],-4);
			$cashier = $CORE_LOCAL->get("CashierNo");
			$lane = $CORE_LOCAL->get("laneno");
			$trans = $CORE_LOCAL->get("transno");
			$sql = "SELECT transID FROM efsnetRequest WHERE [date]='".$today."' AND (PAN LIKE '%".$pan4."') " .
				"AND cashierNo=".$cashier." AND laneNo=".$lane." AND transNo=".$trans;
			$sql = "SELECT transID,cashierNo,laneNo,transNo FROM efsnetRequest WHERE [date]='".$today."' AND (PAN LIKE '%".$pan4."')"; 
			if ($CORE_LOCAL->get("DBMS") == "mysql"){
				$sql = str_replace("[","",$sql);
				$sql = str_replace("]","",$sql);
			}
			$search = PaycardLib::paycard_db_query($sql, $dbTrans);
			$num = PaycardLib::paycard_db_num_rows($search);
			if( $num < 1) {
				PaycardLib::paycard_reset();
				$json['output'] = PaycardLib::paycard_msgBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Card Not Used",
					"That card number was not used in this transaction","[clear] to cancel");
				return $json;
			} else if( $num > 1) {
				PaycardLib::paycard_reset();
				$json['output'] = PaycardLib::paycard_msgBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Multiple Uses",
					"That card number was used more than once in this transaction; select the payment and press VOID","[clear] to cancel");
				return $json;
			}
			$payment = PaycardLib::paycard_db_fetch_row($search);
			return $this->paycard_void($payment['transID'],$lane,$trans,$json);
			break;

		case PaycardLib::PAYCARD_MODE_AUTH:
			if( $validate) {
				if( PaycardLib::paycard_validNumber($this->trans_pan['pan']) != 1) {
					PaycardLib::paycard_reset();
					$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,
						"Invalid Card Number",
						"Swipe again or type in manually",
						"[clear] to cancel");
					return $json;
				} else if( PaycardLib::paycard_accepted($this->trans_pan['pan'],  !PaycardLib::paycard_live(PaycardLib::PAYCARD_TYPE_CREDIT)) != 1) {
					PaycardLib::paycard_reset();
					$json['output'] = PaycardLib::paycard_msgBox(PaycardLib::PAYCARD_TYPE_CREDIT,
						"Unsupported Card Type",
						"We cannot process " . $CORE_LOCAL->get("paycard_issuer") . " cards",
						"[clear] to cancel");
					return $json;
				} else if( PaycardLib::paycard_validExpiration($CORE_LOCAL->get("paycard_exp")) != 1) {
					PaycardLib::paycard_reset();
					$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,
						"Invalid Expiration Date",
						"The expiration date has passed or was not recognized",
						"[clear] to cancel");
					return $json;
				}
			}
			// set initial variables
			//Database::getsubtotals();
			if ($CORE_LOCAL->get("paycard_amount") == 0)
				$CORE_LOCAL->set("paycard_amount",$CORE_LOCAL->get("amtdue"));
			$CORE_LOCAL->set("paycard_id",$CORE_LOCAL->get("LastID")+1); // kind of a hack to anticipate it this way..
			$json['main_frame'] = MiscLib::base_url().'gui-modules/paycardboxMsgAuth.php';
			$json['output'] = '';
			return $json;
			break;
		} // switch mode
	
		// if we're still here, it's an error
		PaycardLib::paycard_reset();
		$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Invalid Mode",
			"This card type does not support that processing mode","[clear] to cancel");
		return $json;

	}

	function paycard_void($transID,$laneNo=-1,$transNo=-1,$json=array()) {
		global $CORE_LOCAL;
		$this->voidTrans = "";
		$this->voidRef = "";
		// situation checking
		if( $CORE_LOCAL->get("CCintegrate") != 1) { // credit card integration must be enabled
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,
				"Card Integration Disabled",
				"Please process credit cards in standalone",
				"[clear] to cancel");
			return $json;
		}
	
		// initialize
		$dbTrans = PaycardLib::paycard_db();
		$today = date('Ymd');
		$cashier = $CORE_LOCAL->get("CashierNo");
		$lane = $CORE_LOCAL->get("laneno");
		$trans = $CORE_LOCAL->get("transno");
		if ($laneNo != -1) $lane = $laneNo;
		if ($transNo != -1) $trans = $transNo;
	
		// look up the request using transID (within this transaction)
		$sql = "SELECT live,PAN,mode,amount,name FROM efsnetRequest 
			WHERE [date]='".$today."' AND cashierNo=".$cashier." AND 
			laneNo=".$lane." AND transNo=".$trans." AND transID=".$transID;
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}
		$search = PaycardLib::paycard_db_query($sql, $dbTrans);
		$num = PaycardLib::paycard_db_num_rows($search);
		if( $num < 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Card request not found, unable to void","[clear] to cancel");
			return $json;
		} else if( $num > 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Card request not distinct, unable to void","[clear] to cancel");
			return $json;
		}
		$request = PaycardLib::paycard_db_fetch_row($search);

		// look up the response
		$sql = "SELECT commErr,httpCode,validResponse,xResponseCode,
			xTransactionID FROM efsnetResponse WHERE [date]='".$today."' 
			AND cashierNo=".$cashier." AND laneNo=".$lane." AND transNo=".$trans." AND transID=".$transID;
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}
		$search = PaycardLib::paycard_db_query($sql, $dbTrans);
		$num = PaycardLib::paycard_db_num_rows($search);
		if( $num < 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Card response not found, unable to void","[clear] to cancel");
			return $json;
		} else if( $num > 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Card response not distinct, unable to void","[clear] to cancel");
			return $json;
		}
		$response = PaycardLib::paycard_db_fetch_row($search);

		// look up any previous successful voids
		$sql = "SELECT transID FROM efsnetRequestMod WHERE [date]=".$today." AND cashierNo=".$cashier." AND laneNo=".$lane." AND transNo=".$trans." AND transID=".$transID
				." AND mode='void' AND xResponseCode=0";
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}
		$search = PaycardLib::paycard_db_query($sql, $dbTrans);
		$voided = PaycardLib::paycard_db_num_rows($search);
		if( $voided > 0) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Unable to Void",
				"Card transaction already voided","[clear] to cancel");
			return $json;
		}

		// look up the transaction tender line-item
		$sql = "SELECT trans_type,trans_subtype,trans_status,voided
		       	FROM localtemptrans WHERE trans_id=" . $transID;
		$search = PaycardLib::paycard_db_query($sql, $dbTrans);
		$num = PaycardLib::paycard_db_num_rows($search);
		if( $num < 1) {
			$sql = "SELECT * FROM localtranstoday WHERE trans_id=".$transID." and emp_no=".$cashier
				." and register_no=".$lane." and trans_no=".$trans;
			$search = PaycardLib::paycard_db_query($sql, $dbTrans);
			$num = PaycardLib::paycard_db_num_rows($search);
			if ($num != 1){
				PaycardLib::paycard_reset();
				$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
					"Transaction item not found, unable to void","[clear] to cancel");
				return $json;
			}
		} else if( $num > 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Transaction item not distinct, unable to void","[clear] to cancel");
			return $json;
		}
		$lineitem = PaycardLib::paycard_db_fetch_row($search);

		// make sure the payment is applicable to void
		if( $response['commErr'] != 0 || $response['httpCode'] != 200 || $response['validResponse'] != 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_msgBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Unable to Void",
				"Card transaction not successful","[clear] to cancel");
			return $json;
		} else if( $request['live'] != PaycardLib::paycard_live(PaycardLib::PAYCARD_TYPE_CREDIT)) {
			// this means the transaction was submitted to the test platform, but we now think we're in live mode, or vice-versa
			// I can't imagine how this could happen (short of serious $_SESSION corruption), but worth a check anyway.. --atf 7/26/07
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Unable to Void",
				"Processor platform mismatch","[clear] to cancel");
			return $json;
		} else if( $response['xResponseCode'] != 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_msgBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Unable to Void",
				"Credit card transaction not approved<br>The result code was " . $response['xResponseCode'],"[clear] to cancel");
			return $json;
		} else if( $response['xTransactionID'] < 1) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Invalid reference number","[clear] to cancel");
			return $json;
		}

		// make sure the tender line-item is applicable to void
		if( $lineitem['trans_type'] != "T" || $lineitem['trans_subtype'] != "CC" ){
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Authorization and tender records do not match $transID","[clear] to cancel");
			return $json;
		} else if( $lineitem['trans_status'] == "V" || $lineitem['voided'] != 0) {
			PaycardLib::paycard_reset();
			$json['output'] = PaycardLib::paycard_errBox(PaycardLib::PAYCARD_TYPE_CREDIT,"Internal Error",
				"Void records do not match","[clear] to cancel");
			return $json;
		}
	
		// save the details
		$CORE_LOCAL->set("paycard_amount",(($request['mode']=='retail_alone_credit') ? -1 : 1) * $request['amount']);
		$CORE_LOCAL->set("paycard_id",$transID);
		$CORE_LOCAL->set("paycard_trans",$cashier."-".$lane."-".$trans);
		$CORE_LOCAL->set("paycard_type",PaycardLib::PAYCARD_TYPE_CREDIT);
		$CORE_LOCAL->set("paycard_mode",PaycardLib::PAYCARD_MODE_VOID);
		$CORE_LOCAL->set("paycard_name",$request['name']);
	
		// display FEC code box
		$CORE_LOCAL->set("inputMasked",1);
		$json['main_frame'] = MiscLib::base_url().'gui-modules/paycardboxMsgVoid.php';
		return $json;
	}

	function handleResponse($authResult){
		global $CORE_LOCAL;
		switch($CORE_LOCAL->get("paycard_mode")){
		case PaycardLib::PAYCARD_MODE_AUTH:
			return $this->handleResponseAuth($authResult);
		case PaycardLib::PAYCARD_MODE_VOID:
			return $this->handleResponseVoid($authResult);
		}
	}

	function handleResponseAuth($authResult){
		global $CORE_LOCAL;
		$xml = new xmlData($authResult['response']);

		// prepare some fields to store the parsed response; we'll add more as we verify it
		$today = date('Ymd'); // numeric date only, it goes in an 'int' field as part of the primary key
		$now = date('Y-m-d H:i:s'); // full timestamp
		$cashierNo = $CORE_LOCAL->get("CashierNo");
		$laneNo = $CORE_LOCAL->get("laneno");
		$transNo = $CORE_LOCAL->get("transno");
		$transID = $CORE_LOCAL->get("paycard_id");
		$cvv2 = $CORE_LOCAL->get("paycard_cvv2");
		$sqlColumns =
			"[date],cashierNo,laneNo,transNo,transID," .
			"[datetime]," .
			"seconds,commErr,httpCode";
		$sqlValues =
			sprintf("%d,%d,%d,%d,%d,",  $today, $cashierNo, $laneNo, $transNo, $transID) .
			sprintf("'%s',",            $now ) .
			sprintf("%f,%d,%d",         $authResult['curlTime'], $authResult['curlErr'], $authResult['curlHTTP']);
		$validResponse = ($xml->isValid()) ? 1 : 0;

		$refNum = $xml->get("ORDER_ID");
		if ($refNum){
			$sqlColumns .= ",refNum";
			$sqlValues .= sprintf(",'%s'",$refNum);
		}
		$responseCode = $xml->get("STATUS");
		if ($responseCode){
			$sqlColumns .= ",xResponseCode";
			$sqlValues .= sprintf(",%d",$responseCode);
		}
		else $validResponse = -3;
		// aren't two separate codes from goemerchant
		$resultCode = $xml->get_first("STATUS");
		if ($resultCode){
			$sqlColumns .= ",xResultCode";
			$sqlValues .= sprintf(",%d",$resultCode);
		}
		$resultMsg = $xml->get_first("AUTH_RESPONSE");
		if ($resultMsg){
			$sqlColumns .= ",xResultMessage";
			$rMsg = $resultMsg;
			if (strlen($rMsg) > 100){
				$rMsg = substr($rMsg,0,100);
			}
			$sqlValues .= sprintf(",'%s'",$rMsg);
		}
		$xTransID = $xml->get("REFERENCE_NUMBER");
		if ($xTransID){
			$sqlColumns .= ",xTransactionID";
			$sqlValues .= sprintf(",'%s'",$xTransID);
		}
		else $validResponse = -3;
		$apprNumber = $xml->get("AUTH_CODE");
		if ($apprNumber){
			$sqlColumns .= ",xApprovalNumber";
			$sqlValues .= sprintf(",'%s'",$apprNumber);
		}
		//else $validResponse = -3;
		// valid credit transactions don't have an approval number
		$sqlColumns .= ",validResponse";
		$sqlValues .= sprintf(",%d",$validResponse);

		$dbTrans = PaycardLib::paycard_db();
		$sql = "INSERT INTO efsnetResponse (" . $sqlColumns . ") VALUES (" . $sqlValues . ")";
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}
		PaycardLib::paycard_db_query($sql, $dbTrans);

		if( $authResult['curlErr'] != CURLE_OK || $authResult['curlHTTP'] != 200){
			if ($authResult['curlHTTP'] == '0'){
				$CORE_LOCAL->set("boxMsg","No response from processor<br />
							The transaction did not go through");
				return PaycardLib::PAYCARD_ERR_PROC;
			}	
			return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_COMM);
		}

		switch ($xml->get("STATUS")){
			case 1: // APPROVED
				$CORE_LOCAL->set("ccTermOut","approval:".str_pad($xml->get("AUTH_CODE"),6,'0',STR_PAD_RIGHT));
				return PaycardLib::PAYCARD_ERR_OK;
			case 2: // DECLINED
				$CORE_LOCAL->set("ccTermOut","approval:denied");
				$CORE_LOCAL->set("boxMsg",$resultMsg);
				break;
			case 0: // ERROR
				$CORE_LOCAL->set("ccTermOut","resettotal");
				$CORE_LOCAL->set("boxMsg","");
				$texts = $xml->get_first("ERROR");
				$CORE_LOCAL->set("boxMsg","Error: $texts");
				break;
			default:
				$CORE_LOCAL->set("boxMsg","An unknown error occurred<br />at the gateway");
		}
		return PaycardLib::PAYCARD_ERR_PROC;
	}

	function handleResponseVoid($authResult){
		global $CORE_LOCAL;
		$xml = new xmlData($authResult['response']);
		// prepare some fields to store the parsed response; we'll add more as we verify it
		$today = date('Ymd'); // numeric date only, it goes in an 'int' field as part of the primary key
		$now = date('Y-m-d H:i:s'); // full timestamp
		$cashierNo = $CORE_LOCAL->get("CashierNo");
		$laneNo = $CORE_LOCAL->get("laneno");
		$transNo = $CORE_LOCAL->get("transno");
		$transID = $CORE_LOCAL->get("paycard_id");
		$amount = $CORE_LOCAL->get("paycard_amount");
		$amountText = number_format(abs($amount), 2, '.', '');
		$refNum = $this->refnum($transID);

		// prepare some fields to store the request and the parsed response; we'll add more as we verify it
		$sqlColumns =
			"[date],cashierNo,laneNo,transNo,transID,[datetime]," .
			"origAmount,mode,altRoute," .
			"seconds,commErr,httpCode";
		$sqlValues =
			sprintf("%d,%d,%d,%d,%d,'%s',",  $today, $cashierNo, $laneNo, $transNo, $transID, $now) .
			sprintf("%s,'%s',%d,",  $amountText, "VOID", 0) .
			sprintf("%f,%d,%d", $authResult['curlTime'], $authResult['curlErr'], $authResult['curlHTTP']);

		$validResponse = ($xml->isValid()) ? 1 : 0;

		$responseCode = $xml->get("STATUS1");
		if ($responseCode){
			$sqlColumns .= ",xResponseCode";
			$sqlValues .= sprintf(",%d",$responseCode);
		}
		else $validResponse = -3;
		$resultCode = $xml->get_first("STATUS1");
		if ($resultCode){
			$sqlColumns .= ",xResultCode";
			$sqlValues .= sprintf(",%d",$resultCode);
		}
		$resultMsg = $xml->get_first("RESPONSE1");
		if ($resultMsg){
			$sqlColumns .= ",xResultMessage";
			$rMsg = $resultMsg;
			if (strlen($rMsg) > 100){
				$rMsg = substr($rMsg,0,100);
			}
			$sqlValues .= sprintf(",'%s'",$rMsg);
		}
		$sqlColumns .= ",origTransactionID";
		$sqlValues .= sprintf(",'%s'",$this->voidTrans);
		$sqlColumns .= ",origRefNum";
		$sqlValues .= sprintf(",'%s'",$this->voidRef);

		$sqlColumns .= ",validResponse";
		$sqlValues .= sprintf(",%d",$validResponse);

		$dbTrans = PaycardLib::paycard_db();
		$sql = "INSERT INTO efsnetRequestMod (" . $sqlColumns . ") VALUES (" . $sqlValues . ")";
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}
		PaycardLib::paycard_db_query($sql, $dbTrans);

		if( $authResult['curlErr'] != CURLE_OK || $authResult['curlHTTP'] != 200){
			if ($authResult['curlHTTP'] == '0'){
				$CORE_LOCAL->set("boxMsg","No response from processor<br />
							The transaction did not go through");
				return PaycardLib::PAYCARD_ERR_PROC;
			}	
			return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_COMM);
		}

		switch ($xml->get("STATUS1")){
			case 1: // APPROVED
				return PaycardLib::PAYCARD_ERR_OK;
			case 2: // DECLINED
				$CORE_LOCAL->set("boxMsg","$resultMsg");
				break;
			case 0: // ERROR
				$CORE_LOCAL->set("boxMsg","");
				$texts = $xml->get_first("ERROR1");
				$CORE_LOCAL->set("boxMsg","Error: $texts");
				break;
			default:
				$CORE_LOCAL->set("boxMsg","An unknown error occurred<br />at the gateway");
		}
		return PaycardLib::PAYCARD_ERR_PROC;
	}

	function cleanup($json){
		global $CORE_LOCAL;
		switch($CORE_LOCAL->get("paycard_mode")){
		case PaycardLib::PAYCARD_MODE_AUTH:
			$CORE_LOCAL->set("ccTender",1); 
			// cast to string. tender function expects string input
			// numeric input screws up parsing on negative values > $0.99
			$amt = "".($CORE_LOCAL->get("paycard_amount")*100);
			PrehLib::tender("CC", $amt);
			$CORE_LOCAL->set("boxMsg","<b>Approved</b><font size=-1><p>Please verify cardholder signature<p>[enter] to continue<br>\"rp\" to reprint slip<br>[void] to cancel and void</font>");
			if ($CORE_LOCAL->get("paycard_amount") <= $CORE_LOCAL->get("CCSigLimit") && $CORE_LOCAL->get("paycard_amount") >= 0){
				$CORE_LOCAL->set("boxMsg","<b>Approved</b><font size=-1><p>No signature required<p>[enter] to continue<br>[void] to cancel and void</font>");
			}	
			break;
		case PaycardLib::PAYCARD_MODE_VOID:
			$v = new Void();
			$v->voidid($CORE_LOCAL->get("paycard_id"));
			$CORE_LOCAL->set("boxMsg","<b>Voided</b><p><font size=-1>[enter] to continue<br>\"rp\" to reprint slip</font>");
			break;	
		}
		$CORE_LOCAL->set("ccCustCopy",0);
		if ($CORE_LOCAL->get("SigCapture") == "" && $CORE_LOCAL->get("paycard_amount") > $CORE_LOCAL->get("CCSigLimit"))
			$json['receipt'] = "ccSlip";
		return $json;
	}

	function doSend($type){
		global $CORE_LOCAL;
		switch($type){
		case PaycardLib::PAYCARD_MODE_AUTH: 
			return $this->send_auth();
		case PaycardLib::PAYCARD_MODE_VOID: 
			return $this->send_void(); 
		default:
			PaycardLib::paycard_reset();
			return $this->setErrorMsg(0);
		}
	}	

	function send_auth(){
		global $CORE_LOCAL;
		// initialize
		$dbTrans = PaycardLib::paycard_db();
		if( !$dbTrans){
			PaycardLib::paycard_reset();
			return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND); // database error, nothing sent (ok to retry)
		}

		$today = date('Ymd'); // numeric date only, it goes in an 'int' field as part of the primary key
		$now = date('Y-m-d H:i:s'); // full timestamp
		$cashierNo = $CORE_LOCAL->get("CashierNo");
		$laneNo = $CORE_LOCAL->get("laneno");
		$transNo = $CORE_LOCAL->get("transno");
		$transID = $CORE_LOCAL->get("paycard_id");
		$amount = $CORE_LOCAL->get("paycard_amount");
		$amountText = number_format(abs($amount), 2, '.', '');
		$mode = (($amount < 0) ? 'retail_alone_credit' : 'retail_sale');
		if ($mode == 'retail_sale' && !GOEMERCH_SETTLE_IMMEDIATE)
			$mode = 'retail_auth';
		$manual = ($CORE_LOCAL->get("paycard_manual") ? 1 : 0);
		$cardPAN = $this->trans_pan['pan'];
		$cardPANmasked = PaycardLib::paycard_maskPAN($cardPAN,0,4);
		$cardIssuer = $CORE_LOCAL->get("paycard_issuer");
		$cardExM = substr($CORE_LOCAL->get("paycard_exp"),0,2);
		$cardExY = substr($CORE_LOCAL->get("paycard_exp"),2,2);
		$cardTr1 = $this->trans_pan['tr1'];
		$cardTr2 = $this->trans_pan['tr2'];
		$cardTr3 = $this->trans_pan['tr3'];
		$cardName = $CORE_LOCAL->get("paycard_name");
		$refNum = $this->refnum($transID);
		$live = 1;
		$cvv2 = $CORE_LOCAL->get("paycard_cvv2");

		$merchantID = GOEMERCH_ID;
		$password = GOEMERCH_PASSWD;
		$gatewayID = GOEMERCH_GATEWAY_ID;
		if ($CORE_LOCAL->get("training") == 1){
			$merchantID = "1264";
			$password = "password";
			$gatewayID = "a91c38c3-7d7f-4d29-acc7-927b4dca0dbe";
			$cardPAN = "4111111111111111";
			$cardPANmasked = "xxxxxxxxxxxxTEST";
			$cardIssuer = "Visa";
			$cardTr1 = False;
			$cardTr2 = False;
			$cardName = "Just Testing";
			$nextyear = mktime(0,0,0,date("m"),date("d"),date("Y")+1);
			$cardExM = date("m",$nextyear);
			$cardExY = date("y",$nextyear);
			$live = 0;
		}
		
		$sendPAN = 0;
		$sendExp = 0;
		$sendTr1 = 0;
		$sendTr2 = 0;
		$magstripe = "";
		if (!$cardTr1 && !$cardTr2){
			$sendPAN = 1;
			$sendExp = 1;
		}
		if ($cardTr1) {
			$sendTr1 = 1;
			$magstripe .= "%".$cardTr1."?";
		}
		if ($cardTr2){
			$sendTr2 = 1;
			$magstripe .= ";".$cardTr2."?";
		}
		if ($cardTr2 && $cardTr3){
			$sendPAN = 1;
			$magstripe .= ";".$cardTr3."?";
		}

		$sqlCols = "sentPAN,sentExp,sentTr1,sentTr2";
		$sqlVals = "$sendPAN,$sendExp,$sendTr1,$sendTr2";
		// store request in the database before sending it
		$sqlCols .= "," . // already defined some sent* columns
			"[date],cashierNo,laneNo,transNo,transID," .
			"[datetime],refNum,live,mode,amount," .
			"PAN,issuer,manual,name";
		$fixedName = PaycardLib::paycard_db_escape($cardName, $dbTrans);
		$sqlVals .= "," . // already defined some sent* values
			sprintf("%d,%d,%d,%d,%d,",        $today, $cashierNo, $laneNo, $transNo, $transID) .
			sprintf("'%s','%s',%d,'%s',%s,",  $now, $refNum, $live, $mode, $amountText) .
			sprintf("'%s','%s',%d,'%s'",           $cardPANmasked, $cardIssuer, $manual,$fixedName);
		$sql = "INSERT INTO efsnetRequest (" . $sqlCols . ") VALUES (" . $sqlVals . ")";
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}

		if( !PaycardLib::paycard_db_query($sql, $dbTrans) ) {
			PaycardLib::paycard_reset();
			return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND); // internal error, nothing sent (ok to retry)
		}

		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
		$xml .= "<TRANSACTION>";
		$xml .= "<FIELDS>";
		$xml .= "<FIELD KEY=\"merchant\">$merchantID</FIELD>";
		if( $password != "" )
			$xml .= "<FIELD KEY=\"password\">$password</FIELD>";
		$xml .= "<FIELD KEY=\"gateway_id\">$gatewayID</FIELD>";
		$xml .= "<FIELD KEY=\"operation_type\">$mode</FIELD>";
		$xml .= "<FIELD KEY=\"order_id\">$refNum</FIELD>";
		$xml .= "<FIELD KEY=\"total\">$amountText</FIELD>";
		if ($magstripe == ""){
			$xml .= "<FIELD KEY=\"card_name\">$cardIssuer</FIELD>";
			$xml .= "<FIELD KEY=\"card_number\">$cardPAN</FIELD>";
			$xml .= "<FIELD KEY=\"card_exp\">".$cardExM.$cardExY."</FIELD>";
		}
		else{
			$xml .= "<FIELD KEY=\"mag_data\">$magstripe</FIELD>";
		}
		if (!empty($cvv2)){
			$xml .= "<FIELD KEY=\"cvv2\">$cvv2</FIELD>";
		}
		if ($cardName != "Customer"){
			$xml .= "<FIELD KEY=\"owner_name\">$cardName</FIELD>";
		}
		$xml .= "<FIELD KEY=\"recurring\">0</FIELD>";
		$xml .= "<FIELD KEY=\"recurring_type\"></FIELD>";
		$xml .= "</FIELDS>";
		$xml .= "</TRANSACTION>";

		$this->GATEWAY = "https://secure.goemerchant.com/secure/gateway/xmlgateway.aspx";
		return $this->curlSend($xml,'POST',True);
	}

	function send_void(){
		global $CORE_LOCAL;
		// initialize
		$dbTrans = PaycardLib::paycard_db();
		if( !$dbTrans){
			PaycardLib::paycard_reset();
			return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND);
		}

		// prepare data for the void request
		$today = date('Ymd'); // numeric date only, it goes in an 'int' field as part of the primary key
		$now = date('Y-m-d H:i:s'); // new timestamp
		$cashierNo = $CORE_LOCAL->get("CashierNo");
		$laneNo = $CORE_LOCAL->get("laneno");
		$transNo = $CORE_LOCAL->get("transno");
		$transID = $CORE_LOCAL->get("paycard_id");
		$amount = $CORE_LOCAL->get("paycard_amount");
		$amountText = number_format(abs($amount), 2, '.', '');
		$mode = 'void';
		$manual = ($CORE_LOCAL->get("paycard_manual") ? 1 : 0);
		$cardPAN = $this->trans_pan['pan'];
		$cardPANmasked = PaycardLib::paycard_maskPAN($cardPAN,0,4);
		$cardIssuer = $CORE_LOCAL->get("paycard_issuer");
		$cardExM = substr($CORE_LOCAL->get("paycard_exp"),0,2);
		$cardExY = substr($CORE_LOCAL->get("paycard_exp"),2,2);
		$cardTr1 = $this->trans_pan['tr1'];
		$cardTr2 = $this->trans_pan['tr2'];
		$cardName = $CORE_LOCAL->get("paycard_name");
		$refNum = $this->refnum($transID);
		$live = 1;

		$this->voidTrans = $transID;
		$this->voidRef = $CORE_LOCAL->get("paycard_trans");
		$temp = explode("-",$this->voidRef);
		$laneNo = $temp[1];
		$transNo = $temp[2];

		$merchantID = GOEMERCH_ID;
		$password = GOEMERCH_PASSWD;
		$gatewayID = GOEMERCH_GATEWAY_ID;
		if ($CORE_LOCAL->get("training") == 1){
			$merchantID = "1264";
			$password = "password";
			$cardPAN = "4111111111111111";
			$gatewayID = "a91c38c3-7d7f-4d29-acc7-927b4dca0dbe";
			$cardPANmasked = "xxxxxxxxxxxxTEST";
			$cardIssuer = "Visa";
			$cardName = "Just Testing";
			$nextyear = mktime(0,0,0,date("m"),date("d"),date("Y")+1);
			$cardExM = date("m",$nextyear);
			$cardExY = date("y",$nextyear);
			$live = 0;
		}

		// look up the TransactionID from the original response (card number and amount should already be in session vars)
		$sql = "SELECT refNum,xTransactionID FROM efsnetResponse WHERE [date]='".$today."'" .
			" AND cashierNo=".$cashierNo." AND laneNo=".$laneNo." AND transNo=".$transNo." AND transID=".$transID;
		if ($CORE_LOCAL->get("DBMS") == "mysql"){
			$sql = str_replace("[","",$sql);
			$sql = str_replace("]","",$sql);
		}
		$result = PaycardLib::paycard_db_query($sql, $dbTrans);
		if( !$result || PaycardLib::paycard_db_num_rows($result) != 1){
			PaycardLib::paycard_reset();
			return $this->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND); 
		}
		$res = PaycardLib::paycard_db_fetch_row($result);
		$TransactionID = $res['xTransactionID'];

		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
		$xml .= "<TRANSACTION>";
		$xml .= "<FIELDS>";
		$xml .= "<FIELD KEY=\"merchant\">$merchantID</FIELD>";
		if ($password != "")
			$xml .= "<FIELD KEY=\"password\">$password</FIELD>";
		$xml .= "<FIELD KEY=\"gateway_id\">$gatewayID</FIELD>";
		$xml .= "<FIELD KEY=\"operation_type\">$mode</FIELD>";
		$xml .= "<FIELD KEY=\"total_number_transactions\">1</FIELD>";
		$xml .= "<FIELD KEY=\"reference_number1\">$TransactionID</FIELD>";
		$xml .= "<FIELD KEY=\"credit_amount1\">$amountText</FIELD>";
		$xml .= "</FIELDS>";
		$xml .= "</TRANSACTION>";

		$this->GATEWAY = "https://secure.goemerchant.com/secure/gateway/xmlgateway.aspx";
		return $this->curlSend($xml,'POST',True);
	}

	// tack time onto reference number for goemerchant order_id
	// field. requires uniqueness, doesn't seem to cycle daily
	function refnum($transID){
		global $CORE_LOCAL;
		$transNo   = (int)$CORE_LOCAL->get("transno");
		$cashierNo = (int)$CORE_LOCAL->get("CashierNo");
		$laneNo    = (int)$CORE_LOCAL->get("laneno");	

		// assemble string
		$ref = "";
		$ref .= date("ymdHis");
		$ref .= "-";
		$ref .= str_pad($cashierNo, 4, "0", STR_PAD_LEFT);
		$ref .= str_pad($laneNo,    2, "0", STR_PAD_LEFT);
		$ref .= str_pad($transNo,   3, "0", STR_PAD_LEFT);
		$ref .= str_pad($transID,   3, "0", STR_PAD_LEFT);
		return $ref;
	}
}

?>
