<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Security;
use yii\helpers\StringHelper;

/**
 * The web Request class represents an HTTP request
 *
 * It encapsulates the $_SERVER variable and resolves its inconsistency among different Web servers.
 * Also it provides an interface to retrieve request parameters from $_POST, $_GET, $_COOKIES and REST
 * parameters sent via other HTTP methods like PUT or DELETE.
 *
 * Request is configured as an application component in [[\yii\web\Application]] by default.
 * You can access that instance via `Yii::$app->request`.
 *
 * @property string $absoluteUrl The currently requested absolute URL. This property is read-only.
 * @property array $acceptableContentTypes The content types ordered by the preference level. The first
 * element represents the most preferred content type.
 * @property array $acceptableLanguages The languages ordered by the preference level. The first element
 * represents the most preferred language.
 * @property string $baseUrl The relative URL for the application.
 * @property array $bodyParams The request parameters given in the request body.
 * @property string $contentType Request content-type. Null is returned if this information is not available.
 * This property is read-only.
 * @property string $cookieValidationKey The secret key used for cookie validation. If it was not set
 * previously, a random key will be generated and used.
 * @property CookieCollection $cookies The cookie collection. This property is read-only.
 * @property string $csrfToken The token used to perform CSRF validation. This property is read-only.
 * @property string $csrfTokenFromHeader The CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned
 * if no such header is sent. This property is read-only.
 * @property HeaderCollection $headers The header collection. This property is read-only.
 * @property string $hostInfo Schema and hostname part (with port number if needed) of the request URL (e.g.
 * `http://www.yiiframework.com`).
 * @property boolean $isAjax Whether this is an AJAX (XMLHttpRequest) request. This property is read-only.
 * @property boolean $isDelete Whether this is a DELETE request. This property is read-only.
 * @property boolean $isFlash Whether this is an Adobe Flash or Adobe Flex request. This property is
 * read-only.
 * @property boolean $isGet Whether this is a GET request. This property is read-only.
 * @property boolean $isHead Whether this is a HEAD request. This property is read-only.
 * @property boolean $isOptions Whether this is a OPTIONS request. This property is read-only.
 * @property boolean $isPatch Whether this is a PATCH request. This property is read-only.
 * @property boolean $isPost Whether this is a POST request. This property is read-only.
 * @property boolean $isPut Whether this is a PUT request. This property is read-only.
 * @property boolean $isSecureConnection If the request is sent via secure channel (https). This property is
 * read-only.
 * @property string $method Request method, such as GET, POST, HEAD, PUT, PATCH, DELETE. The value returned is
 * turned into upper case. This property is read-only.
 * @property string $pathInfo Part of the request URL that is after the entry script and before the question
 * mark. Note, the returned path info is already URL-decoded.
 * @property integer $port Port number for insecure requests.
 * @property array $queryParams The request GET parameter values.
 * @property string $queryString Part of the request URL that is after the question mark. This property is
 * read-only.
 * @property string $rawBody The request body. This property is read-only.
 * @property string $rawCsrfToken The random token for CSRF validation. This property is read-only.
 * @property string $referrer URL referrer, null if not present. This property is read-only.
 * @property string $scriptFile The entry script file path.
 * @property string $scriptUrl The relative URL of the entry script.
 * @property integer $securePort Port number for secure requests.
 * @property string $serverName Server name. This property is read-only.
 * @property integer $serverPort Server port number. This property is read-only.
 * @property string $url The currently requested relative URL. Note that the URI returned is URL-encoded.
 * @property string $userAgent User agent, null if not present. This property is read-only.
 * @property string $userHost User host name, null if cannot be determined. This property is read-only.
 * @property string $userIP User IP address. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Request extends \yii\base\Request
{
	/**
	 * The name of the HTTP header for sending CSRF token.
	 */
	const CSRF_HEADER = 'X-CSRF-Token';
	/**
	 * The length of the CSRF token mask.
	 */
	const CSRF_MASK_LENGTH = 8;


	/**
	 * @var boolean whether to enable CSRF (Cross-Site Request Forgery) validation. Defaults to true.
	 * When CSRF validation is enabled, forms submitted to an Yii Web application must be originated
	 * from the same application. If not, a 400 HTTP exception will be raised.
	 *
	 * Note, this feature requires that the user client accepts cookie. Also, to use this feature,
	 * forms submitted via POST method must contain a hidden input whose name is specified by [[csrfParam]].
	 * You may use [[\yii\web\Html::beginForm()]] to generate his hidden input.
	 *
	 * In JavaScript, you may get the values of [[csrfParam]] and [[csrfToken]] via `yii.getCsrfParam()` and
	 * `yii.getCsrfToken()`, respectively. The [[\yii\web\YiiAsset]] asset must be registered.
	 *
	 * @see Controller::enableCsrfValidation
	 * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
	 */
	public $enableCsrfValidation = true;
	/**
	 * @var string the name of the token used to prevent CSRF. Defaults to '_csrf'.
	 * This property is used only when [[enableCsrfValidation]] is true.
	 */
	public $csrfParam = '_csrf';
	/**
	 * @var array the configuration of the CSRF cookie. This property is used only when [[enableCsrfValidation]] is true.
	 * @see Cookie
	 */
	public $csrfCookie = ['httpOnly' => true];
	/**
	 * @var boolean whether cookies should be validated to ensure they are not tampered. Defaults to true.
	 */
	public $enableCookieValidation = true;
	/**
	 * @var string|boolean the name of the POST parameter that is used to indicate if a request is a PUT, PATCH or DELETE
	 * request tunneled through POST. Default to '_method'.
	 * @see getMethod()
	 * @see getBodyParams()
	 */
	public $methodParam = '_method';
	/**
	 * @var array the parsers for converting the raw HTTP request body into [[bodyParams]].
	 * The array keys are the request `Content-Types`, and the array values are the
	 * corresponding configurations for [[Yii::createObject|creating the parser objects]].
	 * A parser must implement the [[RequestParserInterface]].
	 *
	 * To enable parsing for JSON requests you can use the [[JsonParser]] class like in the following example:
	 *
	 * ```
	 * [
	 *     'application/json' => 'yii\web\JsonParser',
	 * ]
	 * ```
	 *
	 * To register a parser for parsing all request types you can use `'*'` as the array key.
	 * This one will be used as a fallback in case no other types match.
	 *
	 * @see getBodyParams()
	 */
	public $parsers = [];

	/**
	 * @var CookieCollection Collection of request cookies.
	 */
	private $_cookies;
	/**
	 * @var array the headers in this collection (indexed by the header names)
	 */
	private $_headers;


	/**
	 * Resolves the current request into a route and the associated parameters.
	 * @return array the first element is the route, and the second is the associated parameters.
	 * @throws HttpException if the request cannot be resolved.
	 */
	public function resolve()
	{
		$result = Yii::$app->getUrlManager()->parseRequest($this);
		if ($result !== false) {
			list ($route, $params) = $result;
			$_GET = array_merge($_GET, $params);
			return [$route, $_GET];
		} else {
			throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
		}
	}

	/**
	 * Returns the header collection.
	 * The header collection contains incoming HTTP headers.
	 * @return HeaderCollection the header collection
	 */
	public function getHeaders()
	{
		if ($this->_headers === null) {
			$this->_headers = new HeaderCollection;
			if (function_exists('getallheaders')) {
				$headers = getallheaders();
			} elseif (function_exists('http_get_request_headers')) {
				$headers = http_get_request_headers();
			} else {
				foreach ($_SERVER as $name => $value) {
					if (strncmp($name, 'HTTP_', 5) === 0) {
						$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
						$this->_headers->add($name, $value);
					}
				}
				return $this->_headers;
			}
			foreach ($headers as $name => $value) {
				$this->_headers->add($name, $value);
			}
		}

		return $this->_headers;
	}

	/**
	 * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
	 * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
	 * The value returned is turned into upper case.
	 */
	public function getMethod()
	{
		if (isset($_POST[$this->methodParam])) {
			return strtoupper($_POST[$this->methodParam]);
		} elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
			return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
		} else {
			return isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
		}
	}

	/**
	 * Returns whether this is a GET request.
	 * @return boolean whether this is a GET request.
	 */
	public function getIsGet()
	{
		return $this->getMethod() === 'GET';
	}

	/**
	 * Returns whether this is an OPTIONS request.
	 * @return boolean whether this is a OPTIONS request.
	 */
	public function getIsOptions()
	{
		return $this->getMethod() === 'OPTIONS';
	}

	/**
	 * Returns whether this is a HEAD request.
	 * @return boolean whether this is a HEAD request.
	 */
	public function getIsHead()
	{
		return $this->getMethod() === 'HEAD';
	}

	/**
	 * Returns whether this is a POST request.
	 * @return boolean whether this is a POST request.
	 */
	public function getIsPost()
	{
		return $this->getMethod() === 'POST';
	}

	/**
	 * Returns whether this is a DELETE request.
	 * @return boolean whether this is a DELETE request.
	 */
	public function getIsDelete()
	{
		return $this->getMethod() === 'DELETE';
	}

	/**
	 * Returns whether this is a PUT request.
	 * @return boolean whether this is a PUT request.
	 */
	public function getIsPut()
	{
		return $this->getMethod() === 'PUT';
	}

	/**
	 * Returns whether this is a PATCH request.
	 * @return boolean whether this is a PATCH request.
	 */
	public function getIsPatch()
	{
		return $this->getMethod() === 'PATCH';
	}

	/**
	 * Returns whether this is an AJAX (XMLHttpRequest) request.
	 * @return boolean whether this is an AJAX (XMLHttpRequest) request.
	 */
	public function getIsAjax()
	{
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
	}

	/**
	 * Returns whether this is an Adobe Flash or Flex request.
	 * @return boolean whether this is an Adobe Flash or Adobe Flex request.
	 */
	public function getIsFlash()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) &&
			(stripos($_SERVER['HTTP_USER_AGENT'], 'Shockwave') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'Flash') !== false);
	}

	private $_rawBody;

	/**
	 * Returns the raw HTTP request body.
	 * @return string the request body
	 */
	public function getRawBody()
	{
		if ($this->_rawBody === null) {
			$this->_rawBody = file_get_contents('php://input');
		}
		return $this->_rawBody;
	}

	private $_bodyParams;

	/**
	 * Returns the request parameters given in the request body.
	 *
	 * Request parameters are determined using the parsers configured in [[parsers]] property.
	 * If no parsers are configured for the current [[contentType]] it uses the PHP function [[mb_parse_str()]]
	 * to parse the [[rawBody|request body]].
	 * @return array the request parameters given in the request body.
	 * @throws \yii\base\InvalidConfigException if a registered parser does not implement the [[RequestParserInterface]].
	 * @see getMethod()
	 * @see getBodyParam()
	 * @see setBodyParams()
	 */
	public function getBodyParams()
	{
		if ($this->_bodyParams === null) {
			$contentType = $this->getContentType();
			if (isset($_POST[$this->methodParam])) {
				$this->_bodyParams = $_POST;
				unset($this->_bodyParams[$this->methodParam]);
			} elseif (isset($this->parsers[$contentType])) {
				$parser = Yii::createObject($this->parsers[$contentType]);
				if (!($parser instanceof RequestParserInterface)) {
					throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
				}
				$this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
			} elseif (isset($this->parsers['*'])) {
				$parser = Yii::createObject($this->parsers['*']);
				if (!($parser instanceof RequestParserInterface)) {
					throw new InvalidConfigException("The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
				}
				$this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
			} elseif ($this->getMethod() === 'POST') {
				// PHP has already parsed the body so we have all params in $_POST
				$this->_bodyParams = $_POST;
			} else {
				$this->_bodyParams = [];
				mb_parse_str($this->getRawBody(), $this->_bodyParams);
			}
		}
		return $this->_bodyParams;
	}

	/**
	 * Sets the request body parameters.
	 * @param array $values the request body parameters (name-value pairs)
	 * @see getBodyParam()
	 * @see getBodyParams()
	 */
	public function setBodyParams($values)
	{
		$this->_bodyParams = $values;
	}

	/**
	 * Returns the named request body parameter value.
	 * @param string $name the parameter name
	 * @param mixed $defaultValue the default parameter value if the parameter does not exist.
	 * @return mixed the parameter value
	 * @see getBodyParams()
	 * @see setBodyParams()
	 */
	public function getBodyParam($name, $defaultValue = null)
	{
		$params = $this->getBodyParams();
		return isset($params[$name]) ? $params[$name] : $defaultValue;
	}

	/**
	 * Returns POST parameter with a given name. If name isn't specified, returns an array of all POST parameters.
	 *
	 * @param string $name the parameter name
	 * @param mixed $defaultValue the default parameter value if the parameter does not exist.
	 * @return array|mixed
	 */
	public function post($name = null, $defaultValue = null)
	{
		if ($name === null) {
			return $this->getBodyParams();
		} else {
			return $this->getBodyParam($name, $defaultValue);
		}
	}

	private $_queryParams;

	/**
	 * Returns the request parameters given in the [[queryString]].
	 *
	 * This method will return the contents of `$_GET` if params where not explicitly set.
	 * @return array the request GET parameter values.
	 * @see setQueryParams()
	 */
	public function getQueryParams()
	{
		if ($this->_queryParams === null) {
			return $_GET;
		}
		return $this->_queryParams;
	}

	/**
	 * Sets the request [[queryString]] parameters.
	 * @param array $values the request query parameters (name-value pairs)
	 * @see getQueryParam()
	 * @see getQueryParams()
	 */
	public function setQueryParams($values)
	{
		$this->_queryParams = $values;
	}

	/**
	 * Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
	 *
	 * @param string $name the parameter name
	 * @param mixed $defaultValue the default parameter value if the parameter does not exist.
	 * @return array|mixed
	 */
	public function get($name = null, $defaultValue = null)
	{
		if ($name === null) {
			return $this->getQueryParams();
		} else {
			return $this->getQueryParam($name, $defaultValue);
		}
	}

	/**
	 * Returns the named GET parameter value.
	 * If the GET parameter does not exist, the second parameter to this method will be returned.
	 * @param string $name the GET parameter name. If not specified, whole $_GET is returned.
	 * @param mixed $defaultValue the default parameter value if the GET parameter does not exist.
	 * @return mixed the GET parameter value
	 * @see getBodyParam()
	 */
	public function getQueryParam($name, $defaultValue = null)
	{
		$params = $this->getQueryParams();
		return isset($params[$name]) ? $params[$name] : $defaultValue;
	}

	private $_hostInfo;

	/**
	 * Returns the schema and host part of the current request URL.
	 * The returned URL does not have an ending slash.
	 * By default this is determined based on the user request information.
	 * You may explicitly specify it by setting the [[setHostInfo()|hostInfo]] property.
	 * @return string schema and hostname part (with port number if needed) of the request URL (e.g. `http://www.yiiframework.com`)
	 * @see setHostInfo()
	 */
	public function getHostInfo()
	{
		if ($this->_hostInfo === null) {
			$secure = $this->getIsSecureConnection();
			$http = $secure ? 'https' : 'http';
			if (isset($_SERVER['HTTP_HOST'])) {
				$this->_hostInfo = $http . '://' . $_SERVER['HTTP_HOST'];
			} else {
				$this->_hostInfo = $http . '://' . $_SERVER['SERVER_NAME'];
				$port = $secure ? $this->getSecurePort() : $this->getPort();
				if (($port !== 80 && !$secure) || ($port !== 443 && $secure)) {
					$this->_hostInfo .= ':' . $port;
				}
			}
		}

		return $this->_hostInfo;
	}

	/**
	 * Sets the schema and host part of the application URL.
	 * This setter is provided in case the schema and hostname cannot be determined
	 * on certain Web servers.
	 * @param string $value the schema and host part of the application URL. The trailing slashes will be removed.
	 */
	public function setHostInfo($value)
	{
		$this->_hostInfo = rtrim($value, '/');
	}

	private $_baseUrl;

	/**
	 * Returns the relative URL for the application.
	 * This is similar to [[scriptUrl]] except that it does not include the script file name,
	 * and the ending slashes are removed.
	 * @return string the relative URL for the application
	 * @see setScriptUrl()
	 */
	public function getBaseUrl()
	{
		if ($this->_baseUrl === null) {
			$this->_baseUrl = rtrim(dirname($this->getScriptUrl()), '\\/');
		}
		return $this->_baseUrl;
	}

	/**
	 * Sets the relative URL for the application.
	 * By default the URL is determined based on the entry script URL.
	 * This setter is provided in case you want to change this behavior.
	 * @param string $value the relative URL for the application
	 */
	public function setBaseUrl($value)
	{
		$this->_baseUrl = $value;
	}

	private $_scriptUrl;

	/**
	 * Returns the relative URL of the entry script.
	 * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
	 * @return string the relative URL of the entry script.
	 * @throws InvalidConfigException if unable to determine the entry script URL
	 */
	public function getScriptUrl()
	{
		if ($this->_scriptUrl === null) {
			$scriptFile = $this->getScriptFile();
			$scriptName = basename($scriptFile);
			if (basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
				$this->_scriptUrl = $_SERVER['SCRIPT_NAME'];
			} elseif (basename($_SERVER['PHP_SELF']) === $scriptName) {
				$this->_scriptUrl = $_SERVER['PHP_SELF'];
			} elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
				$this->_scriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
			} elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $scriptName)) !== false) {
				$this->_scriptUrl = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
			} elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
				$this->_scriptUrl = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $scriptFile));
			} else {
				throw new InvalidConfigException('Unable to determine the entry script URL.');
			}
		}
		return $this->_scriptUrl;
	}

	/**
	 * Sets the relative URL for the application entry script.
	 * This setter is provided in case the entry script URL cannot be determined
	 * on certain Web servers.
	 * @param string $value the relative URL for the application entry script.
	 */
	public function setScriptUrl($value)
	{
		$this->_scriptUrl = '/' . trim($value, '/');
	}

	private $_scriptFile;

	/**
	 * Returns the entry script file path.
	 * The default implementation will simply return `$_SERVER['SCRIPT_FILENAME']`.
	 * @return string the entry script file path
	 */
	public function getScriptFile()
	{
		return isset($this->_scriptFile) ? $this->_scriptFile : $_SERVER['SCRIPT_FILENAME'];
	}

	/**
	 * Sets the entry script file path.
	 * The entry script file path normally can be obtained from `$_SERVER['SCRIPT_FILENAME']`.
	 * If your server configuration does not return the correct value, you may configure
	 * this property to make it right.
	 * @param string $value the entry script file path.
	 */
	public function setScriptFile($value)
	{
		$this->_scriptFile = $value;
	}

	private $_pathInfo;

	/**
	 * Returns the path info of the currently requested URL.
	 * A path info refers to the part that is after the entry script and before the question mark (query string).
	 * The starting and ending slashes are both removed.
	 * @return string part of the request URL that is after the entry script and before the question mark.
	 * Note, the returned path info is already URL-decoded.
	 * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
	 */
	public function getPathInfo()
	{
		if ($this->_pathInfo === null) {
			$this->_pathInfo = $this->resolvePathInfo();
		}
		return $this->_pathInfo;
	}

	/**
	 * Sets the path info of the current request.
	 * This method is mainly provided for testing purpose.
	 * @param string $value the path info of the current request
	 */
	public function setPathInfo($value)
	{
		$this->_pathInfo = ltrim($value, '/');
	}

	/**
	 * Resolves the path info part of the currently requested URL.
	 * A path info refers to the part that is after the entry script and before the question mark (query string).
	 * The starting slashes are both removed (ending slashes will be kept).
	 * @return string part of the request URL that is after the entry script and before the question mark.
	 * Note, the returned path info is decoded.
	 * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
	 */
	protected function resolvePathInfo()
	{
		$pathInfo = $this->getUrl();

		if (($pos = strpos($pathInfo, '?')) !== false) {
			$pathInfo = substr($pathInfo, 0, $pos);
		}

		$pathInfo = urldecode($pathInfo);

		// try to encode in UTF8 if not so
		// http://w3.org/International/questions/qa-forms-utf-8.html
		if (!preg_match('%^(?:
				[\x09\x0A\x0D\x20-\x7E]              # ASCII
				| [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
				| \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
				| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
				| \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
				| \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
				| [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
				| \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
				)*$%xs', $pathInfo)) {
			$pathInfo = utf8_encode($pathInfo);
		}

		$scriptUrl = $this->getScriptUrl();
		$baseUrl = $this->getBaseUrl();
		if (strpos($pathInfo, $scriptUrl) === 0) {
			$pathInfo = substr($pathInfo, strlen($scriptUrl));
		} elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
			$pathInfo = substr($pathInfo, strlen($baseUrl));
		} elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $scriptUrl) === 0) {
			$pathInfo = substr($_SERVER['PHP_SELF'], strlen($scriptUrl));
		} else {
			throw new InvalidConfigException('Unable to determine the path info of the current request.');
		}

		if ($pathInfo[0] === '/') {
			$pathInfo = substr($pathInfo, 1);
		}

		return (string)$pathInfo;
	}

	/**
	 * Returns the currently requested absolute URL.
	 * This is a shortcut to the concatenation of [[hostInfo]] and [[url]].
	 * @return string the currently requested absolute URL.
	 */
	public function getAbsoluteUrl()
	{
		return $this->getHostInfo() . $this->getUrl();
	}

	private $_url;

	/**
	 * Returns the currently requested relative URL.
	 * This refers to the portion of the URL that is after the [[hostInfo]] part.
	 * It includes the [[queryString]] part if any.
	 * @return string the currently requested relative URL. Note that the URI returned is URL-encoded.
	 * @throws InvalidConfigException if the URL cannot be determined due to unusual server configuration
	 */
	public function getUrl()
	{
		if ($this->_url === null) {
			$this->_url = $this->resolveRequestUri();
		}
		return $this->_url;
	}

	/**
	 * Sets the currently requested relative URL.
	 * The URI must refer to the portion that is after [[hostInfo]].
	 * Note that the URI should be URL-encoded.
	 * @param string $value the request URI to be set
	 */
	public function setUrl($value)
	{
		$this->_url = $value;
	}

	/**
	 * Resolves the request URI portion for the currently requested URL.
	 * This refers to the portion that is after the [[hostInfo]] part. It includes the [[queryString]] part if any.
	 * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
	 * @return string|boolean the request URI portion for the currently requested URL.
	 * Note that the URI returned is URL-encoded.
	 * @throws InvalidConfigException if the request URI cannot be determined due to unusual server configuration
	 */
	protected function resolveRequestUri()
	{
		if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // IIS
			$requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
		} elseif (isset($_SERVER['REQUEST_URI'])) {
			$requestUri = $_SERVER['REQUEST_URI'];
			if ($requestUri !== '' && $requestUri[0] !== '/') {
				$requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
			}
		} elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0 CGI
			$requestUri = $_SERVER['ORIG_PATH_INFO'];
			if (!empty($_SERVER['QUERY_STRING'])) {
				$requestUri .= '?' . $_SERVER['QUERY_STRING'];
			}
		} else {
			throw new InvalidConfigException('Unable to determine the request URI.');
		}
		return $requestUri;
	}

	/**
	 * Returns part of the request URL that is after the question mark.
	 * @return string part of the request URL that is after the question mark
	 */
	public function getQueryString()
	{
		return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
	}

	/**
	 * Return if the request is sent via secure channel (https).
	 * @return boolean if the request is sent via secure channel (https)
	 */
	public function getIsSecureConnection()
	{
		return isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1)
			|| isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
	}

	/**
	 * Returns the server name.
	 * @return string server name
	 */
	public function getServerName()
	{
		return $_SERVER['SERVER_NAME'];
	}

	/**
	 * Returns the server port number.
	 * @return integer server port number
	 */
	public function getServerPort()
	{
		return (int)$_SERVER['SERVER_PORT'];
	}

	/**
	 * Returns the URL referrer, null if not present
	 * @return string URL referrer, null if not present
	 */
	public function getReferrer()
	{
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
	}

	/**
	 * Returns the user agent, null if not present.
	 * @return string user agent, null if not present
	 */
	public function getUserAgent()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	/**
	 * Returns the user IP address.
	 * @return string user IP address
	 */
	public function getUserIP()
	{
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
	}

	/**
	 * Returns the user host name, null if it cannot be determined.
	 * @return string user host name, null if cannot be determined
	 */
	public function getUserHost()
	{
		return isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : null;
	}

	private $_port;

	/**
	 * Returns the port to use for insecure requests.
	 * Defaults to 80, or the port specified by the server if the current
	 * request is insecure.
	 * @return integer port number for insecure requests.
	 * @see setPort()
	 */
	public function getPort()
	{
		if ($this->_port === null) {
			$this->_port = !$this->getIsSecureConnection() && isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 80;
		}
		return $this->_port;
	}

	/**
	 * Sets the port to use for insecure requests.
	 * This setter is provided in case a custom port is necessary for certain
	 * server configurations.
	 * @param integer $value port number.
	 */
	public function setPort($value)
	{
		if ($value != $this->_port) {
			$this->_port = (int)$value;
			$this->_hostInfo = null;
		}
	}

	private $_securePort;

	/**
	 * Returns the port to use for secure requests.
	 * Defaults to 443, or the port specified by the server if the current
	 * request is secure.
	 * @return integer port number for secure requests.
	 * @see setSecurePort()
	 */
	public function getSecurePort()
	{
		if ($this->_securePort === null) {
			$this->_securePort = $this->getIsSecureConnection() && isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 443;
		}
		return $this->_securePort;
	}

	/**
	 * Sets the port to use for secure requests.
	 * This setter is provided in case a custom port is necessary for certain
	 * server configurations.
	 * @param integer $value port number.
	 */
	public function setSecurePort($value)
	{
		if ($value != $this->_securePort) {
			$this->_securePort = (int)$value;
			$this->_hostInfo = null;
		}
	}

	private $_contentTypes;

	/**
	 * Returns the content types acceptable by the end user.
	 * This is determined by the `Accept` HTTP header.
	 * @return array the content types ordered by the preference level. The first element
	 * represents the most preferred content type.
	 */
	public function getAcceptableContentTypes()
	{
		if ($this->_contentTypes === null) {
			if (isset($_SERVER['HTTP_ACCEPT'])) {
				$this->_contentTypes = $this->parseAcceptHeader($_SERVER['HTTP_ACCEPT']);
			} else {
				$this->_contentTypes = [];
			}
		}
		return $this->_contentTypes;
	}

	/**
	 * @param array $value the content types that are acceptable by the end user. They should
	 * be ordered by the preference level.
	 */
	public function setAcceptableContentTypes($value)
	{
		$this->_contentTypes = $value;
	}

	/**
	 * Returns request content-type
	 * The Content-Type header field indicates the MIME type of the data
	 * contained in [[getRawBody()]] or, in the case of the HEAD method, the
	 * media type that would have been sent had the request been a GET.
	 * For the MIME-types the user expects in response, see [[acceptableContentTypes]].
	 * @return string request content-type. Null is returned if this information is not available.
	 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.17
	 * HTTP 1.1 header field definitions
	 */
	public function getContentType()
	{
		return isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
	}

	private $_languages;

	/**
	 * Returns the languages acceptable by the end user.
	 * This is determined by the `Accept-Language` HTTP header.
	 * @return array the languages ordered by the preference level. The first element
	 * represents the most preferred language.
	 */
	public function getAcceptableLanguages()
	{
		if ($this->_languages === null) {
			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				$this->_languages = $this->parseAcceptHeader($_SERVER['HTTP_ACCEPT_LANGUAGE']);
			} else {
				$this->_languages = [];
			}
		}
		return $this->_languages;
	}

	/**
	 * @param array $value the languages that are acceptable by the end user. They should
	 * be ordered by the preference level.
	 */
	public function setAcceptableLanguages($value)
	{
		$this->_languages = $value;
	}

	/**
	 * Parses the given `Accept` (or `Accept-Language`) header.
	 * This method will return the acceptable values ordered by their preference level.
	 * @param string $header the header to be parsed
	 * @return array the accept values ordered by their preference level.
	 */
	protected function parseAcceptHeader($header)
	{
		$accepts = [];
		$n = preg_match_all('/\s*([\w\/\-\*]+)\s*(?:;\s*q\s*=\s*([\d\.]+))?[^,]*/', $header, $matches, PREG_SET_ORDER);
		for ($i = 0; $i < $n; ++$i) {
			if (!empty($matches[$i][1])) {
				$accepts[] = [$matches[$i][1], isset($matches[$i][2]) ? (float)$matches[$i][2] : 1, $i];
			}
		}
		usort($accepts, function ($a, $b) {
			if ($a[1] > $b[1]) {
				return -1;
			} elseif ($a[1] < $b[1]) {
				return 1;
			} elseif ($a[0] === $b[0]) {
				return $a[2] > $b[2] ? 1 : -1;
			} elseif ($a[0] === '*/*') {
				return 1;
			} elseif ($b[0] === '*/*') {
				return -1;
			} else {
				$wa = $a[0][strlen($a[0]) - 1] === '*';
				$wb = $b[0][strlen($b[0]) - 1] === '*';
				if ($wa xor $wb) {
					return $wa ? 1 : -1;
				} else {
					return $a[2] > $b[2] ? 1 : -1;
				}
			}
		});
		$result = [];
		foreach ($accepts as $accept) {
			$result[] = $accept[0];
		}
		return array_unique($result);
	}

	/**
	 * Returns the user-preferred language that should be used by this application.
	 * The language resolution is based on the user preferred languages and the languages
	 * supported by the application. The method will try to find the best match.
	 * @param array $languages a list of the languages supported by the application. If this is empty, the current
	 * application language will be returned without further processing.
	 * @return string the language that the application should use.
	 */
	public function getPreferredLanguage(array $languages = [])
	{
		if (empty($languages)) {
			return Yii::$app->language;
		}
		foreach ($this->getAcceptableLanguages() as $acceptableLanguage) {
			$acceptableLanguage = str_replace('_', '-', strtolower($acceptableLanguage));
			foreach ($languages as $language) {
				$language = str_replace('_', '-', strtolower($language));
				// en-us==en-us, en==en-us, en-us==en
				if ($language === $acceptableLanguage || strpos($acceptableLanguage, $language . '-') === 0 || strpos($language, $acceptableLanguage . '-') === 0) {
					return $language;
				}
			}
		}
		return reset($languages);
	}

	/**
	 * Returns the cookie collection.
	 * Through the returned cookie collection, you may access a cookie using the following syntax:
	 *
	 * ~~~
	 * $cookie = $request->cookies['name']
	 * if ($cookie !== null) {
	 *     $value = $cookie->value;
	 * }
	 *
	 * // alternatively
	 * $value = $request->cookies->getValue('name');
	 * ~~~
	 *
	 * @return CookieCollection the cookie collection.
	 */
	public function getCookies()
	{
		if ($this->_cookies === null) {
			$this->_cookies = new CookieCollection($this->loadCookies(), [
				'readOnly' => true,
			]);
		}
		return $this->_cookies;
	}

	/**
	 * Converts `$_COOKIE` into an array of [[Cookie]].
	 * @return array the cookies obtained from request
	 */
	protected function loadCookies()
	{
		$cookies = [];
		if ($this->enableCookieValidation) {
			$key = $this->getCookieValidationKey();
			foreach ($_COOKIE as $name => $value) {
				if (is_string($value) && ($value = Security::validateData($value, $key)) !== false) {
					$cookies[$name] = new Cookie([
						'name' => $name,
						'value' => @unserialize($value),
					]);
				}
			}
		} else {
			foreach ($_COOKIE as $name => $value) {
				$cookies[$name] = new Cookie([
					'name' => $name,
					'value' => $value,
				]);
			}
		}
		return $cookies;
	}

	private $_cookieValidationKey;

	/**
	 * @return string the secret key used for cookie validation. If it was not set previously,
	 * a random key will be generated and used.
	 */
	public function getCookieValidationKey()
	{
		if ($this->_cookieValidationKey === null) {
			$this->_cookieValidationKey = Security::getSecretKey(__CLASS__ . '/' . Yii::$app->id);
		}
		return $this->_cookieValidationKey;
	}

	/**
	 * Sets the secret key used for cookie validation.
	 * @param string $value the secret key used for cookie validation.
	 */
	public function setCookieValidationKey($value)
	{
		$this->_cookieValidationKey = $value;
	}

	/**
	 * @var Cookie
	 */
	private $_csrfCookie;

	/**
	 * Returns the unmasked random token used to perform CSRF validation.
	 * This token is typically sent via a cookie. If such a cookie does not exist, a new token will be generated.
	 * @return string the random token for CSRF validation.
	 * @see enableCsrfValidation
	 */
	public function getRawCsrfToken()
	{
		if ($this->_csrfCookie === null) {
			$this->_csrfCookie = $this->getCookies()->get($this->csrfParam);
			if ($this->_csrfCookie === null) {
				$this->_csrfCookie = $this->createCsrfCookie();
				Yii::$app->getResponse()->getCookies()->add($this->_csrfCookie);
			}
		}

		return $this->_csrfCookie->value;
	}

	private $_csrfToken;

	/**
	 * Returns the token used to perform CSRF validation.
	 *
	 * This token is a masked version of [[rawCsrfToken]] to prevent [BREACH attacks](http://breachattack.com/).
	 * This token may be passed along via a hidden field of an HTML form or an HTTP header value
	 * to support CSRF validation.
	 *
	 * @return string the token used to perform CSRF validation.
	 */
	public function getCsrfToken()
	{
		if ($this->_csrfToken === null) {
			// the mask doesn't need to be very random
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.';
			$mask = substr(str_shuffle(str_repeat($chars, 5)), 0, self::CSRF_MASK_LENGTH);

			$token = $this->getRawCsrfToken();
			// The + sign may be decoded as blank space later, which will fail the validation
			$this->_csrfToken = str_replace('+', '.', base64_encode($mask . $this->xorTokens($token, $mask)));
		}
		return $this->_csrfToken;
	}

	/**
	 * Returns the XOR result of two strings.
	 * If the two strings are of different lengths, the shorter one will be padded to the length of the longer one.
	 * @param string $token1
	 * @param string $token2
	 * @return string the XOR result
	 */
	private function xorTokens($token1, $token2)
	{
		$n1 = StringHelper::byteLength($token1);
		$n2 = StringHelper::byteLength($token2);
		if ($n1 > $n2) {
			$token2 = str_pad($token2, $n1, $token2);
		} elseif ($n1 < $n2) {
			$token1 = str_pad($token1, $n2, $token1);
		}
		return $token1 ^ $token2;
	}

	/**
	 * @return string the CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned if no such header is sent.
	 */
	public function getCsrfTokenFromHeader()
	{
		$key = 'HTTP_' . str_replace('-', '_', strtoupper(self::CSRF_HEADER));
		return isset($_SERVER[$key]) ? $_SERVER[$key] : null;
	}

	/**
	 * Creates a cookie with a randomly generated CSRF token.
	 * Initial values specified in [[csrfCookie]] will be applied to the generated cookie.
	 * @return Cookie the generated cookie
	 * @see enableCsrfValidation
	 */
	protected function createCsrfCookie()
	{
		$options = $this->csrfCookie;
		$options['name'] = $this->csrfParam;
		$options['value'] = Security::generateRandomKey();
		return new Cookie($options);
	}

	/**
	 * Performs the CSRF validation.
	 * The method will compare the CSRF token obtained from a cookie and from a POST field.
	 * If they are different, a CSRF attack is detected and a 400 HTTP exception will be raised.
	 * This method is called in [[Controller::beforeAction()]].
	 * @return boolean whether CSRF token is valid. If [[enableCsrfValidation]] is false, this method will return true.
	 */
	public function validateCsrfToken()
	{
		$method = $this->getMethod();
		// only validate CSRF token on non-"safe" methods http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.1.1
		if (!$this->enableCsrfValidation || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
			return true;
		}
		$trueToken = $this->getCookies()->getValue($this->csrfParam);
		$token = $this->getBodyParam($this->csrfParam);
		return $this->validateCsrfTokenInternal($token, $trueToken)
			|| $this->validateCsrfTokenInternal($this->getCsrfTokenFromHeader(), $trueToken);
	}

	private function validateCsrfTokenInternal($token, $trueToken)
	{
		$token = base64_decode(str_replace('.', '+', $token));
		$n = StringHelper::byteLength($token);
		if ($n <= self::CSRF_MASK_LENGTH) {
			return false;
		}
		$mask = StringHelper::byteSubstr($token, 0, self::CSRF_MASK_LENGTH);
		$token = StringHelper::byteSubstr($token, self::CSRF_MASK_LENGTH, $n - self::CSRF_MASK_LENGTH);
		$token = $this->xorTokens($mask, $token);
		return $token === $trueToken;
	}
}
