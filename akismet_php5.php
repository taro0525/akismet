<?php

/**
 *	This is plugin for Akismet SPAM filtering using the Akismet PHP5 Class
 *	@author : vincent3569
 *	@version 1.0.0
 *
 *	based on the initial Akismet plugin for ZenPhoto
 *	@authors : GameDudeX, Thinkdreams, Kieran O'Shea
 *
 *	Akismet PHP5 Class
 *	@author		Alex Potsides (http://www.achingbrain.net)
 *	@version	0.4
 */


 /**
 *	This implements the ZenPhoto standard SpamFilter class for the Akismet spam filter.
 *
 */
 class SpamFilter {

	/**
	 *	The SpamFilter class instantiation function.
	 *	@return SpamFilter
	 */
	function SpamFilter() {
		setOptionDefault('Akismet_key', '');
		setOptionDefault('Forgiving', 0);
	}

	/**
	 *	The admin options interface
	 *	called from admin Options tab
	 *	returns an array of the option names the theme supports
	 *	the array is indexed by the option name. The value for each option is an array:
	 *		'type' => 0 says for admin to use a standard textbox for the option
	 *		'type' => 1 says for admin to use a standard checkbox for the option
	 *		'type' => 2 will cause admin to call handleOption to generate the HTML for the option
	 *		'desc' => text to be displayed for the option description.
	 * @return array
	 */
	function getOptionsSupported() {
		return array(
			gettext('Akismet key') => array('key' => 'Akismet_key', 'type' => 0, 'desc' => gettext('Proper operation requires an Akismet API key obtained by signing up for a <a href="http://akismet.com/">Akismet</a> account.')),
			gettext('Forgiving') => array('key' => 'Forgiving', 'type' => 1, 'desc' => gettext('Mark suspected SPAM for moderation rather than as SPAM'))
		);
	}

	/**
	 *	Handles custom formatting of options for Admin (of which, there are none for Akismet)
	 *	@param string $option the option name of the option to be processed
	 *	@param mixed $currentValue the current value of the option (the "before" value)
	 */
	function handleOption($option, $currentValue) {
	}

	/**
	 *	The function for processing a message to see if it might be SPAM
	 *		returns:
	 *			0 if the message is SPAM
	 *			1 if the message might be SPAM (it will be marked for moderation)
	 *			2 if the message is not SPAM
	 *	@param string $author	Author field from the posting
	 *	@param string $email	Email field from the posting
	 *	@param string $website	Website field from the posting
	 *	@param string $body		the text of the comment
	 *	@param object $receiver	the object on which the post was made : not used by the plugin
	 *	@param string $ip		the IP address of the comment poster : not used by the plugin
	 *	@return int
	 */
	function filterMessage($author, $email, $website, $body, $receiver, $ip) {

		$zp_galerieUrl = FULLWEBPATH;	// Set the webpath for the Akismet server
		$zp_akismetKey = getOption('Akismet_key');
		$forgive = getOption('Forgiving');
		$result = 2;	// good comment until proven bad

		$akismet = new Akismet($zp_galerieUrl, $zp_akismetKey);
		
		$akismet->setCommentAuthor($author);
		$akismet->setCommentAuthorEmail($email);
		$akismet->setCommentAuthorURL($website);
		$akismet->setCommentContent($body);
		$akismet->setCommentType('comment');

		if($akismet->isCommentSpam()) {
			// Message is spam according to Akismet
			$result = $forgive;
		} else {
			// Message is not spam according to Akismet
		}

		return $result;
	}
}	// end of class SpamFilter


/**
 *	Akismet anti-comment spam service
 *	The class in this package allows use of the Akismet anti-comment spam service in any PHP5 application.
 *	This service performs a number of checks on submitted data and returns whether or not the data is likely to be spam.
 *	Please note that in order to use this class, you must have a vaild Akismet API Key (http://akismet.com/).
 *
 *	Please be aware that this class is PHP5 only.  Attempts to run it under PHP4 will most likely fail.
 *
 *	@package	akismet
 *	@author		Alex Potsides (http://www.achingbrain.net)
 *	@version	0.4
 *	@license	http://www.opensource.org/licenses/bsd-license.php
 */

/**
 *	The Akismet PHP5 Class
 *	This class takes the functionality from the Akismet WordPress plugin written by Matt Mullenweg (http://photomatt.net/) and allows it to be integrated into any PHP5 application or website.
 *	The original plugin is available on the Akismet website (http://akismet.com/development/).
 *
 *	<b>Usage:</b>
 *	<code>
 *		$akismet = new Akismet('http://www.example.com/blog/', 'YOUR_AKISMET_API_KEY');
 *		$akismet->setCommentAuthor('viagra-test-123');
 *		$akismet->setCommentAuthorEmail('test@example.com');
 *		$akismet->setCommentAuthorURL('http://www.example.com/');
 *		$akismet->setCommentContent('This is a test comment');
 *		if($akismet->isCommentSpam())
 *			// store the comment but mark it as spam (in case of a mis-diagnosis)
 *		else
 *			// store the comment normally
 *	</code>
 *
 *	Optionally you may wish to check if your WordPress API key is valid as in the example below.
 *	
 *	<code>
 *		$akismet = new Akismet('http://www.example.com/blog/', 'YOUR_AKISMET_API_KEY');
 *		
 *		if($akismet->isKeyValid()) {
 *			// api key is okay
 *		} else {
 *			// api key is invalid
 *		}
 *	</code>
 */
class Akismet {
	private $version = '0.4';
	private $akismetAPIKey;
	private $blogURL;
	private $comment;
	private $apiPort;
	private $akismetServer;
	private $akismetVersion;

	// This prevents some potentially sensitive information from being sent accross the wire.
	private $ignore = array(
		'HTTP_COOKIE',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED_HOST',
		'HTTP_MAX_FORWARDS',
		'HTTP_X_FORWARDED_SERVER',
		'REDIRECT_STATUS',
		'SERVER_PORT',
		'PATH',
		'DOCUMENT_ROOT',
		'SERVER_ADMIN',
		'QUERY_STRING',
		'PHP_SELF'
	);

	/**
	 *	@param	string	$blogURL		The URL of your blog.
	 *	@param	string	$akismetAPIKey	Akismet API key.
	 */
	public function __construct($blogURL, $akismetAPIKey) {
		$this->blogURL = $blogURL;
		$this->akismetAPIKey = $akismetAPIKey;

		// Set some default values
		$this->apiPort = 80;
		$this->akismetServer = 'rest.akismet.com';
		$this->akismetVersion = '1.1';

		// Start to populate the comment data
		$this->comment['blog'] = $blogURL;
		$this->comment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

		if(isset($_SERVER['HTTP_REFERER'])) {
			$this->comment['referrer'] = $_SERVER['HTTP_REFERER'];
		}

		/**
		 *	This is necessary if the server PHP5 is running on has been set up to run PHP4 and
		 *	PHP5 concurently and is actually running through a separate proxy al a these instructions:
		 *	http://www.schlitt.info/applications/blog/archives/83_How_to_run_PHP4_and_PHP_5_parallel.html and http://wiki.coggeshall.org/37.html
		 *	Otherwise the user_ip appears as the IP address of the PHP4 server passing the requests to the PHP5 one...
		 */
		$this->comment['user_ip'] = $_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR') ? $_SERVER['REMOTE_ADDR'] : getenv('HTTP_X_FORWARDED_FOR');
	}

	/**
	 *	Makes a request to the Akismet service to see if the API key passed to the constructor is valid.
	 *	Use this method if you suspect your API key is invalid.
	 *	@return bool	True is if the key is valid, false if not.
	 */
	public function isKeyValid() {
		// Check to see if the key is valid
		$response = $this->sendRequest('key=' . $this->akismetAPIKey . '&blog=' . $this->blogURL, $this->akismetServer, '/' . $this->akismetVersion . '/verify-key');

		return $response[1] == 'valid';
	}

	// makes a request to the Akismet service
	private function sendRequest($request, $host, $path) {
		$http_request  = "POST " . $path . " HTTP/1.0\r\n";
		$http_request .= "Host: " . $host . "\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
		$http_request .= "Content-Length: " . strlen($request) . "\r\n";
		$http_request .= "User-Agent: Akismet PHP5 Class " . $this->version . " | Akismet/1.11\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;

		$socketWriteRead = new SocketWriteRead($host, $this->apiPort, $http_request);
		$socketWriteRead->send();

		return explode("\r\n\r\n", $socketWriteRead->getResponse(), 2);
	}

	// Formats the data for transmission
	private function getQueryString() {
		foreach($_SERVER as $key => $value) {
			if(!in_array($key, $this->ignore)) {
				if($key == 'REMOTE_ADDR') {
					$this->comment[$key] = $this->comment['user_ip'];
				} else {
					$this->comment[$key] = $value;
				}
			}
		}

		$query_string = '';

		foreach($this->comment as $key => $data) {
			if(!is_array($data)) {
				$query_string .= $key . '=' . urlencode(stripslashes($data)) . '&';
			}
		}

		return $query_string;
	}

	/**
	 *	Tests for spam.
	 *	Uses the web service provided by Akismet to see whether or not the submitted comment is spam. Returns a boolean value.
	 *	@return		bool	True if the comment is spam, false if not
	 *	@throws		Will throw an exception if the API key passed to the constructor is invalid.
	 */
	public function isCommentSpam() {
		$response = $this->sendRequest($this->getQueryString(), $this->akismetAPIKey . '.rest.akismet.com', '/' . $this->akismetVersion . '/comment-check');
		if(($response[1] == 'invalid') && (!$this->isKeyValid())) {
			throw new exception('The Akismet API key passed to the Akismet constructor is invalid. Please obtain a valid one from http://akismet.com/');
		}

		return ($response[1] == 'true');
	}

	/**
	 *	Submit spam that is incorrectly tagged as ham.
	 *	Using this function will make you a good citizen as it helps Akismet to learn from its mistakes. This will improve the service for everybody.
	 */
	public function submitSpam() {
		$this->sendRequest($this->getQueryString(), $this->akismetAPIKey . '.' . $this->akismetServer, '/' . $this->akismetVersion . '/submit-spam');
	}

	/**
	 *	Submit ham that is incorrectly tagged as spam.
	 *	Using this function will make you a good citizen as it helps Akismet to learn from its mistakes. This will improve the service for everybody.
	 */
	public function submitHam() {
		$this->sendRequest($this->getQueryString(), $this->akismetAPIKey . '.' . $this->akismetServer, '/' . $this->akismetVersion . '/submit-ham');
	}

	/**
	 *	To override the user IP address when submitting spam/ham later on
	 *	@param string $userip	An IP address. Optional.
	 */
	public function setUserIP($userip) {
		$this->comment['user_ip'] = $userip;
	}

	/**
	 *	To override the referring page when submitting spam/ham later on
	 *	@param string $referrer	The referring page. Optional.
	 */
	public function setReferrer($referrer) {
		$this->comment['referrer'] = $referrer;
	}

	/**
	 *	A permanent URL referencing the blog post the comment was submitted to.
	 *	@param string $permalink	The URL. Optional.
	 */
	public function setPermalink($permalink) {
		$this->comment['permalink'] = $permalink;
	}

	/**
	 *	The type of comment being submitted.
	 *	May be blank, comment, trackback, pingback, or a made up value like "registration" or "wiki".
	 */
	public function setCommentType($commentType) {
		$this->comment['comment_type'] = $commentType;
	}

	/**
	 *	The name that the author submitted with the comment.
	 */
	public function setCommentAuthor($commentAuthor) {
		$this->comment['comment_author'] = $commentAuthor;
	}

	/**
	 *	The email address that the author submitted with the comment.
	 *	The address is assumed to be valid.
	 */
	public function setCommentAuthorEmail($authorEmail) {
		$this->comment['comment_author_email'] = $authorEmail;
	}

	/**
	 *	The URL that the author submitted with the comment.
	 */	
	public function setCommentAuthorURL($authorURL) {
		$this->comment['comment_author_url'] = $authorURL;
	}

	/**
	 *	The comment's body text.
	 */
	public function setCommentContent($commentBody) {
		$this->comment['comment_content'] = $commentBody;
	}

	/**
	 *	Defaults to 80
	 */
	public function setAPIPort($apiPort) {
		$this->apiPort = $apiPort;
	}

	/**
	 *	Defaults to rest.akismet.com
	 */
	public function setAkismetServer($akismetServer) {
		$this->akismetServer = $akismetServer;
	}

	/**
	 *	Defaults to '1.1'
	 */
	public function setAkismetVersion($akismetVersion) {
		$this->akismetVersion = $akismetVersion;
	}
}

/**
 *	Utility class used by Akismet
 *	This class is used by Akismet to do the actual sending and receiving of data. It opens a connection to a remote host, sends some data and the reads the response and makes it available to the calling program.
 *	The code that makes up this class originates in the Akismet WordPress plugin, which is available on the Akismet website.
 *	N.B. It is not necessary to call this class directly to use the Akismet class. This is included here mainly out of a sense of completeness.
 *
 *	@package	akismet
 *	@name		SocketWriteRead
 *	@version	0.1
 *	@author		Alex Potsides
 *	@link		http://www.achingbrain.net/
 */
class SocketWriteRead {
	private $host;
	private $port;
	private $request;
	private $response;
	private $responseLength;
	private $errorNumber;
	private $errorString;

	/**
	 *	@param	string	$host			The host to send/receive data.
	 *	@param	int		$port			The port on the remote host.
	 *	@param	string	$request		The data to send.
	 *	@param	int		$responseLength	The amount of data to read. Defaults to 1160 bytes.
	 */
	public function __construct($host, $port, $request, $responseLength = 1160) {
		$this->host = $host;
		$this->port = $port;
		$this->request = $request;
		$this->responseLength = $responseLength;
		$this->errorNumber = 0;
		$this->errorString = '';
	}

	/**
	 *	Sends the data to the remote host.
	 *	@throws	An exception is thrown if a connection cannot be made to the remote host.
	 */
	public function send() {
		$this->response = '';
		$fs = fsockopen($this->host, $this->port, $this->errorNumber, $this->errorString, 3);

		if($this->errorNumber != 0) {
			throw new Exception('Error connecting to host: ' . $this->host . ' Error number: ' . $this->errorNumber . ' Error message: ' . $this->errorString);
		}

		if($fs !== false) {
			@fwrite($fs, $this->request);
			while(!feof($fs)) {
				$this->response .= fgets($fs, $this->responseLength);
			}
			fclose($fs);
		}
	}

	/**
	 *	Returns the server response text
	 *	@return	string
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 *	Returns the error number
	 *	If there was no error, 0 will be returned.
	 *	@return int
	 */
	public function getErrorNumner() {
		return $this->errorNumber;
	}

	/**
	 *	Returns the error string
	 *	If there was no error, an empty string will be returned.
	 *	@return string
	 */
	public function getErrorString() {
		return $this->errorString;
	}
}

?>