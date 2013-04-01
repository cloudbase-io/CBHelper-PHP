<?php
/*! \mainpage cloudbase.io PHP Helper Class Reference
 *
 * \section intro_sec Introduction
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, version 2, as published by
 * the Free Software Foundation.<br/><br/>
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
 * for more details.<br/><br/>
 
 * You should have received a copy of the GNU General Public License
 * along with this program; see the file COPYING.  If not, write to the Free
 * Software Foundation, 59 Temple Place - Suite 330, Boston, MA
 * 02111-1307, USA.<br/><br/>
 *
 * \section install_sec Getting Started
 *
 * This class depends on the php Curl extension. Packages are available for most linux distribution
 * to install through your package manager of choice:<br/><br/>
 *
 * This full reference is a companion to <a href="/documentation/php" target="_blank">
 * the tutorial on the cloudbase.io website<a/>
 */
 
class CBLogLevel
{
    const CBLogLevelDebug = "DEBUG";
    const CBLogLevelInfo = "INFO";
    const CBLogLevelWarning = "WARNING";
    const CBLogLevelError = "ERROR";
    const CBLogLevelFatal = "FATAL";
    const CBLogLevelEvent = "EVENT";
}

class CBHelper
{
    const OUTPUT_FORMAT     = "json"; 
    const CLOUDBASE_API_URL = "http://api.cloudbase.io";
    const CBLOG_DEFAULT_CATEGORY = "DEFAULT";

    private $appcode        = "";
    private $appsecret      = "";
    private $password       = "";

    public $device_name     = "CBHelper-php";
    public $device_model    = "0.1b";
    public $device_uniq     = "";
	
	public $auth_username	= "";
	public $auth_password	= "";
	
	private $sessionid		= "";
	private $language		= "";
	
	/**
	 * creates a new CBHelper object for the given app-code.
	 * @param string the application code on cloudbase.io
	 * @param string the unique key generated by cloudbase.io for the application. This is always visible in your control panel
	 * @param string The md5 of the application password as specified on cloudbase.io
	 * 
	 * @return CBHelper
	 */
    public function __construct($app_code, $app_secret, $app_password)
    {
        $this->appcode = $app_code;
        $this->appsecret = $app_secret;
        $this->password = $app_password;
		$lan = (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "en");
		$cur_lan = explode(",", $lan);
		$this->language = $cur_lan[0];

        if ($this->device_uniq == "")
            $this->device_uniq = $this->get_device_uniq();

		// register the device and receive the session id from the cloudbase.io server
        $new_device = $this->register_device();
		$this->sessionid = $new_device["message"]["sessionid"];
    }

    public function __destruct()
    {
    }
	
	/**
	 * Returns the session id generated by cloudbase when the helper classes is created and 
	 * registered as a "device"
	 * 
	 * @return string The session id from cloudbase.io
	 */
	public function get_session_id()
	{
		return $this->sessionid;
	}

	/**
	 * sends a line to your application log on cloudbase.io. Each line has a level and a category.
	 * If the category parameter is not specified cloudbase.io will automatically put the line in the DEFAULT category.
	 * @param string the text you want to log
	 * @param CBLogLevel the severity level of the log message
	 * @param string the category for the log message, by default this is set to DEFAULT
	 */
    public function log_line($log_text, $level, $category = self::CBLOG_DEFAULT_CATEGORY)
    {
        $post_data = array(
            "category" => $category, // the category for the line (DEFAULT)
            "level" => $level, // the severity of the log line - a value from CBLogLevel
            "device_name" => $this->device_name, // debug information about the device version and software
            "device_model" => $this->device_model, // debug information about the device version and software
            "log_line" => $log_text
        );

        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/log";

        return $this->send_http_post($post_data, $url, "log");
    }
	
	/**
	 * sends cloudbase a log navigation message. this is used to generate analytics about how users interact with your application
	 * @param string a unique code identifying the screen
	 */
	public function log_navigation($screen_name)
	{
		$post_data = array(
			"screen_name" => $screen_name, // the name of the screen/webpage being opened by the user
			"session_id" => $this->sessionid // the session id received from cloudbase.io when the device is registered
		);
		
		$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/lognavigation";
		
		return $this->send_http_post($post_data, $url, "log-navigation");
	}
	
	/**
	 * send an email using the specified template to the given recipient.
	 * templates for emails can be configured from your cloudbase.io control panel and allow full HTML templates
	 * and parametrization by using variables within your template code such as %myvar%
	 * @param string the template code as generated on cloudbase.io
	 * @param string the email address of the recipient
	 * @param string the subject of the email
	 * @param array The variables to fill your template. (if required)
	 */
	public function send_email($template, $recipient, $subject, $vars = array())
	{
		$post_data = array(
			"template_code" => $template, // the template created on cloudbase.io
			"recipient" => $recipient, 
			"subject" => $subject,
			"variables" => $vars
		);
		
		$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/email";
		
		return $this->send_http_post($post_data, $url, "email");
	}

    private function register_device()
    {
        $data_item = array(
        	"device_type" => "php",
            "device_name" => $this->device_name,
            "device_model" => $this->device_model,
            "language" => $this->language
        );
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/register";

        return $this->send_http_post($data_item, $url, "register-device");
    }

	/**
	 * inserts a new document in a collection in your CloudBase. Additionally you can send a number of files
	 * to be attached to the document. The files are an associative array in the following format "filename" => "@/file/path"
	 * @param array an array containing the data for your document
	 * @param string the name of the collection for your data
	 * @param array an associative array containing the list of files to be attached
	 */
    public function insert_document($data_item, $collection_name, $files = array())
    {
        $action = "insert";
        
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/" . $collection_name . "/" . $action;

        // The cloudbase.io data APIs expect an array of objects to be inserted. If what we have been given is 
        // an associative array then it's a single object - insert it into an array 
        if (CBHelper::is_assoc($data_item))
            return $this->send_http_post(array( $data_item ), $url, "data", $files);
        else
            return $this->send_http_post($data_item, $url, "data", $files);
    }

	/**
	 * updates a document in your cloudbase. the $data_item parameter also needs to contain a key called <strong>cb_search_key</string>
	 * This will be used to find the document. The document will be overwritten.
	 * @param array the item to be updated with the new values and the additional cb_search_key value
	 * @param string the name of the collection containing the document to be updated
	 */
    public function update_document($data_item, $collection_name)
    {
        $action = "update";
        
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/" . $collection_name . "/" . $action;

        return $this->send_http_post($data_item, $url, "data");
    }

	/**
	 * looks up a document within a collection and returns an array of all the documents found
	 * @param string the name of the collection to search into
	 * @param array a number of search conditions to run the query on the collection. If this parameter is empty then the full collection will be returned
	 * 
	 * @return array an array or arrays containing the documents returned by your query
	 */
    public function search_document($collection_name, $search_conditions = array())
    {
        $action = "search";
        
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/" . $collection_name . "/" . $action;

		// for a full description and structure of the possible search conditions see the
		// documentation online at http://cloudbase.io/documentation/rest-apis#CloudBase
        $post_data = array( "cb_search_key" => $search_conditions );
        
        return $this->send_http_post( $post_data, $url, "data");
    }
	
	/**
	 * Calls the cloud database APIs and runs the Data Aggregation Commands pver the collection.
	 * @param string $collection_name The name of the cloud database collection
	 * @param array $aggregate_conditions An ordered array of aggregate conditions to run over the data
	 * 
	 * @return array An associative array with the result of the data aggregation commands
	 */
	public function search_aggregate_document($collection_name, $aggregate_conditions) {
		$action = "aggregate";
        
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/" . $collection_name . "/" . $action;

		// for a full description and structure of the possible search conditions see the
		// documentation online at http://cloudbase.io/documentation/rest-apis#CloudBase
        $post_data = array( "cb_aggregate_key" => $aggregate_conditions );
        
        return $this->send_http_post( $post_data, $url, "data");
	}
	
	/**
	 * Downloads a file attached to a document in a collection. 
	 * @param string $file_id The id of the file to be downloaded from the cb_files field in a document
	 * 
	 * @return the data of the file
	 */
	public function download_file($file_id) {
		$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/file/" . $file_id;
		
		return $this->send_http_post( array(), $url, "download");
	}

	/**
	 * executes a CloudFunction and returns the output if any is produced.
	 * @param string the CloudFunction code
	 * @param array additional parameters to pass to your CloudFunction. they will be accessible as $_POST parameters
	 * 
	 * @return array an associative array containing the output from your CloudFunction if any is produced
	 */
    public function call_cloudfunction($fcode, $params = array())
    {
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/cloudfunction/" . $fcode;

		// the additional http parametersare sent straight through the http_post method
        return $this->send_http_post(array(), $url, "cloudfunction", $params);
    }
	
	/**
	 * executes an applet and returns the output if any is produced.
	 * @param string the applet code
	 * @param array additional parameters to pass to the applet
	 * 
	 * @return array an associative array containing the output from the applet if any is produced
	 */
	public function call_applet($fcode, $params = array())
    {
    	$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/applet/" . $fcode;
		
		return $this->send_http_post(array(), $url, "applet", $params);
    }
	
	/**
	 * preapres a payment with PayPal retrieving an express checkout token. The token and checkout url
	 * are then returned.
	 * @param array The payment details structure as specified in the documentation
	 * @param string The possible values are "live" or "sandbox" depending whether we want to use the testing or live environment
	 * @param string The 3 letter ISO code representing the transaction currency
	 * @param string The CloudFunction code to be executed once the payment is completed successfully
	 * @param string The CloudFunction code to be executed if the payment is cancelled
	 * @param string The url PayPal should redirect to once the transaction is complete. by default paypal will forward to the cloudbase
	 *   api page to update the status of the payment
	 * @param string The url PayPal should redirect to if the transaction is cancelled.
	 * 
	 * @return an associative array containing the PayPal token, checkout url and the cloudbase.io payment id
	 */
	public function prepare_paypal_purchase($payment_data, $environment, $currency, $completed_cloudfunction = "", $cancelled_cloudfunction = "", $completed_url = "", $cancelled_url = "")
	{
		$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/paypal/prepare";
		
		$post_data = array(
			"purchase_details" => $payment_data,
			"environment" => $environment,
			"currency" => $currency,
			"type" => "purchase"
		);
		
		if ($completed_cloudfunction != "")
			$post_data["completed_cloudfunction"] = $completed_cloudfunction;
		if ($cancelled_cloudfunction != "")
			$post_data["cancelled_cloudfunction"] = $cancelled_cloudfunction;
		if ($completed_url != "")
			$post_data["payment_completed_url"] = $completed_url;
		if ($cancelled_url)
			$post_data["payment_cancelled_url"] = $cancelled_url;
		
		return $this->send_http_post($post_data, $url, "paypal", array());
	}
	
	/**
	 * Updates a payment to the status returned by PayPal
	 * 
	 * @param string The cloudbase.io payment id returned by the prepare_paypal_purchase method
	 * @param bool Whether the transaction was successfull or not
	 * @param string The unique invoice number generated by the client application
	 * 
	 * @return The payment id updated
	 */
	public function update_paypal_payment_status($payment_id, $paypal_success, $invoice_number) {
		$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/paypal/update-status";
		$url .= "?invoice_number=" . $invoice_number;
		$url .= "&payment_id=" . $payment_id;
		$url .= "&paypal=" . ($paypal_success?"paid":"cancel");
		
		return $this->send_http_post(array(), $url, "paypal", array());
	}
	
	/**
	 * Returnes the details of a payment sent through the prepare_paypal_purchase method
	 * 
	 * @param string The cloudbase.io payment id
	 * 
	 * @return An associative array with all of the details of the purchase 
	 */
	public function get_paypal_payment_details($payment_id)
	{
		$url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/paypal/payment-details";
		
		$post_data = array(
			"payment_id" => $payment_id
		);
		
		return $this->send_http_post($post_data, $url, "paypal", array());
	}

	// this function is used to retrieve the analytics for a cloudbase.io app. This is an internal API
	// and it's not documented yet.
    public function stats($stats_type) {
        $url = self::CLOUDBASE_API_URL . "/" . $this->appcode . "/stats/" . $stats_type;
        print $url;
        return $this->send_http_post(array( "empty" => "param"), $url);
    }

	/**
	 * This function converts an object to an associagive array.
	 * 
	 * @param Object a php class
	 * 
	 * @return array An associative array representation of the given object
	 */
    public static function object_to_array($d) {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }

        if (is_array($d)) {
            return array_map(__FUNCTION__, $d);
        }
        else {
            return $d;
        }
    }

    // sends an http post to the cloudbase.io APIs
    private function send_http_post($post_data, $url, $function_name = "", $additional_params = array())
    {
    	// prepare the default parameters for a request
    	$prepared_data = array(
            "app_uniq" => $this->appsecret,
            "app_pwd" => $this->password,
            "device_uniq" => $this->device_uniq,
            "output_format" => self::OUTPUT_FORMAT,
            "post_data" => json_encode( $post_data )
            );
		
		// if the application is set to require authentication then we set the 
		// username and password fields to be sent.
		if ($this->auth_username != "") {
			$prepared_data["auth_username"] = $this->auth_username;
			$prepared_data["auth_password"] = $this->auth_password;
		}

		// merge the array we have prepared of parameters with the additional http post parameters
		// given to the call - these may contain the file attachments 
		$prepared_data = array_merge($prepared_data, $additional_params);
		//var_dump($prepared_data);
		
		// start the http post
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $prepared_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		
        // execute the post and get the response output
        $curloutput = curl_exec($curl);
		
		//error_log($curloutput);
		
		// get the http status and error message
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        // we are done. close the curl connection
        curl_close($curl);
		if ( $function_name == "download" ) {
			return $curloutput;
		} else {
	        $output_array = json_decode($curloutput, true);
			$output = $output_array[$function_name];
			$output["httpStatus"] = $http_status;
			
			return $output;
		}
    }

	// returnes a uniquq id to identify the server we are running on
    private function get_device_uniq()
    {
        return (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] . " - " . $_SERVER['HTTP_USER_AGENT'] : $this->device_name);
    }

	// returns whether the object given is an associative array
    private static function is_assoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

?>
