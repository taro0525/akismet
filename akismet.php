<?php
/**
 *	This is plugin for Akismet SPAM filtering using the Akismet PHP5 Class
 * @author Vincent Bourganel (vincent3569)
 * @version 2.0.1
 * @package plugins
 * @subpackage spam
 *
 *	based on the initial Akismet plugin for ZenPhoto
 *	authors GameDudeX, Thinkdreams, Kieran O'Shea
 *
 *	based on Akismet PHP5 Class
 *	author Alex Potsides (http://www.achingbrain.net)
 *	version 0.5
 *
 *	changelog :
 *	- version 2.0
 *		- rewrite to use zenphoto plugin architecture
 *		- needs the ZenPhoto official release 1.4.4 or more
 *	- version 1.2.0
 *		- needs the ZenPhoto official release 1.4.2 or more
 *		- migration on release 0.5 of Akismet PHP5 Class from Alex Potsides
 *		- increase the timeout of akismet (hack of Akismet PHP5 Class)
 *		- if Akismet API key is invalid or if there is a timeout from akismet server
 *			- comments are automatically moderated
 *			- actions on comments (marked as spam/approved in comments list) are done on Zenphoto even if the returns are not sent to Akismet
 *	- version 1.1.0
 *		- needs the ZenPhoto official release 1.4.2 or more
 *		- add functions to send feedback to Akismet and to signal a false negative
 *		- add functions to send feedback to Akismet and to signal a false positive
 *		- add function to see if the provided Akismet API key in the admin tabs is a valid one
 *	- version 1.0.0
 *		- intial release of the plugin
 */

$plugin_is_filter = 5|CLASS_PLUGIN;
$plugin_description = gettext("Akismet SPAM filter");
$plugin_author = "Vincent Bourganel (vincent3569)";

$plugin_disable = (isset($_zp_spamFilter) && !getoption('zp_plugin_akismet')) ? sprintf(gettext('Only one SPAM handler plugin may be enalbed. <a href="#%1$s"><code>%1$s</code></a> is already enabled.'), $_zp_spamFilter->name) : '';

$option_interface = 'AkismetSpamFilter';

if ($plugin_disable) {
	setOption('zp_plugin_akismet', 0);
} else {
	$_zp_spamFilter = new AkismetSpamFilter();
}


/**
 *	Register filters
 */
zp_register_filter('comment_disapprove', 'submitSpam');
zp_register_filter('comment_approve', 'submitHam');

/**
 *	The function to send feedback to Akismet and to signal a false negative
 *	@param obj $comment		Comment object
 */
function submitSpam($comment) {

	$id = $comment->getOwnerID();
	$author = $comment->getName();
	$email = $comment->getEmail();
	$website = $comment->getWebsite();
	$body = $comment->getComment();

	$_zp_spamFilter = new AkismetSpamFilter();
	$_zp_spamFilter->feedbackMessage($id, $author, $email, $website, $body, false);

	return $comment;
}

/**
 *	The function to send feedback to Akismet and to signal a false positive
 *	@param obj $comment		Comment object
 */
function submitHam($comment) {

	$id = $comment->getOwnerID();
	$author = $comment->getName();
	$email = $comment->getEmail();
	$website = $comment->getWebsite();
	$body = $comment->getComment();

	$_zp_spamFilter = new AkismetSpamFilter();
	$_zp_spamFilter->feedbackMessage($id, $author, $email, $website, $body, true);

	return $comment;
}


/**
 * The Class AkismetSpamFilter
 */
class AkismetSpamFilter {

	var $name = 'akismet';

	/**
	 * The AkismetSpamFilter class instantiation function.
	 *
	 * @return AkismetSpamFilter
	 */
	function __construct() {
		setOptionDefault('Akismet_key', '');
		setOptionDefault('Forgiving', 0);
	}

	function displayName() {
		return $this->name;
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
		$isValidKey = $this->isValidKey();
		if (getOption('Akismet_key') && ($isValidKey == 1)) {
			$msg = '<p class="messagebox">' . gettext('You have provided a valid Akismet key.') . '</p>';
		}
		if (!(getOption('Akismet_key')) || ($isValidKey == 2)) {
			$msg = '<p class="errorbox">' . gettext('You need to provide a valid Akismet key.') . '</p>';
		}
		if (getOption('Akismet_key') && ($isValidKey == 3)) {
			$msg = '<p class="notebox">' . gettext('Akismet server is too busy : your Akismet API Key can\'t be checked. Please, try again later.') . '</p>';
		}
		return array(
			gettext('Akismet key') => array('key' => 'Akismet_key', 'type' => 0, 'desc' => '<p>' . gettext('Proper operation requires an Akismet API key obtained by signing up for a <a href="http://akismet.com/">Akismet</a> account.') . '</p>' . $msg),
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
	 *
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

		try {
			if ($akismet->isCommentSpam()) {
				// Message is spam according to Akismet
				$result = $forgive;
				if (DEBUG_FILTERS) {debugLog('====> $akismet->isCommentSpam : this is a SPAM. Author : ' . $author . '. Email : ' . $email);}
			} else {
				// Message is not spam according to Akismet
				/*if (DEBUG_FILTERS)*/ {debugLog('====> $akismet->isCommentSpam : this is not a SPAM. Author : ' . $author . '. Email : ' . $email);}
			}
		} catch (Exception $e) {
			// Due to an error with Akismet server, message is marked for moderation
			$result = 1;
			/*if (DEBUG_FILTERS)*/ {debugLog('====> $akismet->Exception : ' . $e->getMessage() . '. Author : ' . $author . '. Email : ' . $email);}
		}

		return $result;
	}

	/**
	 *	The function to see if the provided API key is a valid one
	 *	@returns : int
	 *			1 if API Key is valid
	 *			2 if API Key is invalid
	 *			3 if API Key is valid can't be checked
	 */ 
	function isValidKey() {

		$zp_galerieUrl = FULLWEBPATH;	// Set the webpath for the Akismet server
		$zp_akismetKey = getOption('Akismet_key');

		$akismet = new Akismet($zp_galerieUrl, $zp_akismetKey);

		try {
			if ($akismet->isKeyValid()) {
				return 1;
			} else {
				return 2;
			}
		} catch (Exception $e) {
			if (DEBUG_FILTERS) {debugLog('====> $akismet->Exception : ' . $e->getMessage());}
			return 3;
		}
	}

	/**
	 *	The function to send feedback to Akismet and to signal either
	 *		- a false positive
	 *		- a false negative
	 *	@param string $author		Author field from the posting
	 *	@param string $email		Email field from the posting
	 *	@param string $website		Website field from the posting
	 *	@param string $body			the text of the comment
	 *	@param bool $goodMessage	false to set a comment as a spam
	 *								true to set a moderated message as a valid one
	 */
	function feedbackMessage($id, $author, $email, $website, $body, $goodMessage) {

		$zp_galerieUrl = FULLWEBPATH;	// Set the webpath for the Akismet server
		$zp_akismetKey = getOption('Akismet_key');

		$akismet = new Akismet($zp_galerieUrl, $zp_akismetKey);

		$akismet->setCommentAuthor($author);
		$akismet->setCommentAuthorEmail($email);
		$akismet->setCommentAuthorURL($website);
		$akismet->setCommentContent($body);
		$akismet->setCommentType('comment');

		try {
			if (!($goodMessage)) {
				$akismet->submitSpam();
				if (DEBUG_FILTERS) {debugLog('====> $akismet->submitSpam : IdComment : ' . $id . '. Author : ' . $author);}
			} else {
				$akismet->submitHam();
				if (DEBUG_FILTERS) {debugLog('====> $akismet->submitHam : IdComment : ' . $id . '. Author : ' . $author);}
			}
		} catch (Exception $e) {
			if (DEBUG_FILTERS) {debugLog('====> $akismet->Exception : ' . $e->getMessage() . '. Author : ' . $author . '. Email : ' . $email);}
		}
	}

}	// end of class AkismetSpamFilter



/****
 * Akismet anti-comment spam service
 *
 * The class in this package allows use of the {@link http://akismet.com Akismet} anti-comment spam service in any PHP5 application.
 * This service performs a number of checks on submitted data and returns whether or not the data is likely to be spam.
 * Please note that in order to use this class, you must have a vaild {@link http://wordpress.com/api-keys/ WordPress API key}.
 * They are free for non/small-profit types and getting one will only take a couple of minutes.
 * For commercial use, please {@link http://akismet.com/commercial/ visit the Akismet commercial licensing page}.
 * Please be aware that this class is PHP5 only.  Attempts to run it under PHP4 will most likely fail.
 * See the Akismet class documentation page linked to below for usage information.
 *
 * @package		akismet
 * @author		Alex Potsides, {@link http://www.achingbrain.net http://www.achingbrain.net}
 * @version		0.5
 * @copyright	Alex Potsides, {@link http://www.achingbrain.net http://www.achingbrain.net}
 * @license		http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * The Akismet PHP5 Class
 *
 * This class takes the functionality from the Akismet WordPress plugin written by {@link http://photomatt.net/ Matt Mullenweg} and allows it to be integrated into any PHP5 application or website.
 * The original plugin is {@link http://akismet.com/download/ available on the Akismet website}.
 *
 * <code>
 * $akismet = new Akismet('http://www.example.com/blog/', 'aoeu1aoue');
 * $akismet->setCommentAuthor('viagra-test-123');
 * $akismet->setCommentAuthorEmail('test@example.com');
 * $akismet->setCommentAuthorURL('http://www.example.com/');
 * $akismet->setCommentContent($comment);
 * $akismet->setPermalink('http://www.example.com/blog/alex/someurl/');
 *
 * if($akismet->isCommentSpam()) {
 *   // store the comment but mark it as spam (in case of a mis-diagnosis)
 * } else {
 *   // store the comment normally
 * }
 * </code>
 *
 * Optionally you may wish to check if your WordPress API key is valid as in the example below.
 *
 * <code>
 * $akismet = new Akismet('http://www.example.com/blog/', 'aoeu1aoue');
 *
 * if($akismet->isKeyValid()) {
 *   // api key is okay
 * } else {
 *   // api key is invalid
 * }
 * </code>
 *
 * @package	akismet
 * @name	Akismet
 * @version	0.5
 * @author	Alex Potsides
 * @link	http://www.achingbrain.net/
 */
class Akismet {
	private $version = '0.5';
	private $wordPressAPIKey;
	private $blogURL;
	private $comment;
	private $apiPort;
	private $akismetServer;
	private $akismetVersion;
	private $requestFactory;

	// This prevents some potentially sensitive information from being sent accross the wire.
	private $ignore = array('HTTP_COOKIE',
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
							'PHP_SELF' );

	/**
	 * @param	string	$blogURL			The URL of your blog.
	 * @param	string	$wordPressAPIKey	WordPress API key.
	 */
	public function __construct($blogURL, $wordPressAPIKey) {
		$this->blogURL = $blogURL;
		$this->wordPressAPIKey = $wordPressAPIKey;

		// Set some default values
		$this->apiPort = 80;
		$this->akismetServer = 'rest.akismet.com';
		$this->akismetVersion = '1.1';
		$this->requestFactory = new SocketWriteReadFactory();

		// Start to populate the comment data
		$this->comment['blog'] = $blogURL;

		if(isset($_SERVER['HTTP_USER_AGENT'])) {
			$this->comment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}

		if(isset($_SERVER['HTTP_REFERER'])) {
			$this->comment['referrer'] = $_SERVER['HTTP_REFERER'];
		}

		/*
		 * This is necessary if the server PHP5 is running on has been set up to run PHP4 and
		 * PHP5 concurently and is actually running through a separate proxy al a these instructions:
		 * http://www.schlitt.info/applications/blog/archives/83_How_to_run_PHP4_and_PHP_5_parallel.html and http://wiki.coggeshall.org/37.html
		 * Otherwise the user_ip appears as the IP address of the PHP4 server passing the requests to the PHP5 one...
		 */
		if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR')) {
			$this->comment['user_ip'] = $_SERVER['REMOTE_ADDR'];
		} else {
			$this->comment['user_ip'] = getenv('HTTP_X_FORWARDED_FOR');
		}
	}

	/**
	 * Makes a request to the Akismet service to see if the API key passed to the constructor is valid.
	 * Use this method if you suspect your API key is invalid.
	 *
	 * @return bool	True is if the key is valid, false if not.
	 */
	public function isKeyValid() {
		// Check to see if the key is valid
		$response = $this->sendRequest('key=' . $this->wordPressAPIKey . '&blog=' . $this->blogURL, $this->akismetServer, '/' . $this->akismetVersion . '/verify-key');

		return ($response[1] == 'valid');
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

		$requestSender = $this->requestFactory->createRequestSender();
		$response = $requestSender->send($host, $this->apiPort, $http_request);

		return explode("\r\n\r\n", $response, 2);
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
	 * Tests for spam.
	 * Uses the web service provided by {@link http://www.akismet.com Akismet} to see whether or not the submitted comment is spam. Returns a boolean value.
	 *
	 * @return	bool	True if the comment is spam, false if not
	 * @throws	Will throw an Exception if the API key passed to the constructor is invalid.
	 */
	public function isCommentSpam() {
		$response = $this->sendRequest($this->getQueryString(), $this->wordPressAPIKey . '.' . $this->akismetServer, '/' . $this->akismetVersion . '/comment-check');

		if($response[1] == 'invalid' && !$this->isKeyValid()) {
			throw new Exception('The Wordpress API key passed to the Akismet constructor is invalid. Please obtain a valid one from http://wordpress.com/api-keys/');
		}

		return ($response[1] == 'true');
	}

	/**
	 * Submit spam that is incorrectly tagged as ham.
	 * Using this function will make you a good citizen as it helps Akismet to learn from its mistakes.  This will improve the service for everybody.
	 */
	public function submitSpam() {
		$this->sendRequest($this->getQueryString(), $this->wordPressAPIKey . '.' . $this->akismetServer, '/' . $this->akismetVersion . '/submit-spam');
	}

	/**
	 * Submit ham that is incorrectly tagged as spam.
	 * Using this function will make you a good citizen as it helps Akismet to learn from its mistakes.  This will improve the service for everybody.
	 */
	public function submitHam() {
		$this->sendRequest($this->getQueryString(), $this->wordPressAPIKey . '.' . $this->akismetServer, '/' . $this->akismetVersion . '/submit-ham');
	}

	/**
	 * To override the user IP address when submitting spam/ham later on
	 * @param string $userip	An IP address. Optional.
	 */
	public function setUserIP($userip) {
		$this->comment['user_ip'] = $userip;
	}

	/**
	 * To override the referring page when submitting spam/ham later on
	 * @param string $referrer	The referring page. Optional.
	 */
	public function setReferrer($referrer) {
		$this->comment['referrer'] = $referrer;
	}

	/**
	 * A permanent URL referencing the blog post the comment was submitted to.
	 * @param string $permalink	The URL. Optional.
	 */
	public function setPermalink($permalink) {
		$this->comment['permalink'] = $permalink;
	}

	/**
	 * The type of comment being submitted.
	 * May be blank, comment, trackback, pingback, or a made up value like "registration" or "wiki".
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
	 * The email address that the author submitted with the comment.
	 * The address is assumed to be valid.
	 */
	public function setCommentAuthorEmail($authorEmail) {
		$this->comment['comment_author_email'] = $authorEmail;
	}

	/**
	 * The URL that the author submitted with the comment.
	 */
	public function setCommentAuthorURL($authorURL) {
		$this->comment['comment_author_url'] = $authorURL;
	}

	/**
	 * The comment's body text.
	 */
	public function setCommentContent($commentBody) {
		$this->comment['comment_content'] = $commentBody;
	}

	/**
	 * Lets you override the user agent used to submit the comment.
	 * you may wish to do this when submitting ham/spam.
	 * Defaults to $_SERVER['HTTP_USER_AGENT']
	 */
	public function setCommentUserAgent($userAgent) {
		$this->comment['user_agent'] = $userAgent;
	}

	/**
	 * Defaults to 80
	 */
	public function setAPIPort($apiPort) {
		$this->apiPort = $apiPort;
	}

	/**
	 * Defaults to rest.akismet.com
	 */
	public function setAkismetServer($akismetServer) {
		$this->akismetServer = $akismetServer;
	}

	/**
	 * Defaults to '1.1'
	 * @param string $akismetVersion
	 */
	public function setAkismetVersion($akismetVersion) {
		$this->akismetVersion = $akismetVersion;
	}

	/**
	 * Used by unit tests to mock transport layer
	 * @param AkismetRequestFactory $requestFactory
	 */
	public function setRequestFactory($requestFactory) {
		$this->requestFactory = $requestFactory;
	}
}

/**
 * Used internally by Akismet
 *
 * This class is used by Akismet to do the actual sending and receiving of data.  It opens a connection to a remote host, sends some data and the reads the response and makes it available to the calling program.
 * The code that makes up this class originates in the Akismet WordPress plugin, which is {@link http://akismet.com/download/ available on the Akismet website}.
 * N.B. It is not necessary to call this class directly to use the Akismet class.
 *
 * @package	akismet
 * @name	SocketWriteRead
 * @version	0.5
 * @author	Alex Potsides
 * @link	http://www.achingbrain.net/
 */
class SocketWriteRead implements AkismetRequestSender {
	private $response;
	private $errorNumber;
	private $errorString;

	public function __construct() {
		$this->errorNumber = 0;
		$this->errorString = '';
	}

	/**
	 * Sends the data to the remote host.
	 *
	 * @param	string	$host			The host to send/receive data.
	 * @param	int		$port			The port on the remote host.
	 * @param	string	$request		The data to send.
	 * @param	int		$responseLength	The amount of data to read.  Defaults to 1160 bytes.
	 * @throws	An Exception is thrown if a connection cannot be made to the remote host.
	 * @returns	The server response
	 */
	public function send($host, $port, $request, $responseLength = 1160) {
		$response = '';

		$fs = fsockopen($host, $port, $this->errorNumber, $this->errorString, 10);		/* hack vincent3569 to increase timeout delay*/

		if($this->errorNumber != 0) {
			throw new Exception('Error connecting to host: ' . $host . '. Error number: ' . $this->errorNumber . '. Error message: ' . $this->errorString);
		}

		if($fs !== false) {
			@fwrite($fs, $request);

			while(!feof($fs)) {
				$response .= fgets($fs, $responseLength);
			}

			fclose($fs);
		}

		return $response;
	}

	/**
	 * Returns the server response text
	 * @return	string
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * Returns the error number
	 * If there was no error, 0 will be returned.
	 * @return int
	 */
	public function getErrorNumner() {
		return $this->errorNumber;
	}

	/**
	 * Returns the error string
	 * If there was no error, an empty string will be returned.
	 * @return string
	 */
	public function getErrorString() {
		return $this->errorString;
	}
}

/**
 * Used internally by the Akismet class and to mock the Akismet anti spam service in the unit tests.
 * N.B. It is not necessary to call this class directly to use the Akismet class.
 *
 * @package	akismet
 * @name	SocketWriteReadFactory
 * @version	0.5
 * @author	Alex Potsides
 * @link	http://www.achingbrain.net/
 */
class SocketWriteReadFactory implements AkismetRequestFactory {

	public function createRequestSender() {
		return new SocketWriteRead();
	}
}

/**
 * Used internally by the Akismet class and to mock the Akismet anti spam service in the unit tests.
 * N.B. It is not necessary to implement this class to use the Akismet class.
 *
 * @package	akismet
 * @name	AkismetRequestSender
 * @version	0.5
 * @author	Alex Potsides
 * @link	http://www.achingbrain.net/
 */
interface AkismetRequestSender {

	/**
	 * Sends the data to the remote host.
	 *
	 * @param	string	$host			The host to send/receive data.
	 * @param	int		$port			The port on the remote host.
	 * @param	string	$request		The data to send.
	 * @param	int		$responseLength	The amount of data to read.  Defaults to 1160 bytes.
	 * @throws	An Exception is thrown if a connection cannot be made to the remote host.
	 * @returns	The server response
	 */
	public function send($host, $port, $request, $responseLength = 1160);
}

/**
 * Used internally by the Akismet class and to mock the Akismet anti spam service in the unit tests.
 * N.B. It is not necessary to implement this class to use the Akismet class.
 *
 * @package	akismet
 * @name	AkismetRequestFactory
 * @version	0.5
 * @author	Alex Potsides
 * @link	http://www.achingbrain.net/
 */
interface AkismetRequestFactory {

	public function createRequestSender();
}
?>