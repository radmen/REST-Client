<?php

namespace Rest;

class Client {

  protected $mHost;
  
  protected $mCookieName = 'cookies';
  
  private $mDebug = false;
  
  protected $mDebugFileHandler = null;
  
  protected $mHeaders = array();
  
  public function __construct($pHost = null) {
    
    if(null !== $pHost) {
      $this->setHost($pHost);
    }
  }
  
  /**
   * Sets dubg mode
   * 
   * If debug mode is turned on every sent and received data will be saved
   * to file: curl_{class_name}.log (ex. curl_rest_client.log)
   *
   * @param boolean $pState
   * @return Client 
   */
  public final function debug($pState) {
    $this->mDebug = (bool) $pState;
    
    return $this;
  }
  
  /**
   * Sets up additional headers which will be send with *every* request
   *
   * @param array $pHeaders
   * @return Client 
   */
  public function setHeaders(array $pHeaders = array()) {
    $this->mHeaders = $pHeaders;
    
    return $this;
  }
  
  /**
   * Sets up adress to API
   *
   * @param string $pHost
   * @return Client 
   */
  public function setHost($pHost) {
    $parsed_url = parse_url($pHost);
    
    if(true === empty($parsed_url['scheme'])) {
      $parsed_url['scheme'] = 'http';
    }

    $pHost = $parsed_url['scheme'].'://'.$parsed_url['host'].$parsed_url['path'];
    
    if('/' === substr($pHost, -1)) {
      $pHost = substr($pHost, 0, -1);
    }
    
    $this->mHost = $pHost;
    
    return $this;
  }
  
  /**
   * Returns path to cookies file
   *
   * @return string
   */
  protected function getCookiesPath() {
	  return realpath('temp').DIRECTORY_SEPARATOR.$this->mCookieName.'.txt';
  }
  
  /**
   * Sets up specific cookies file name
   * 
   * Default is 'cookies'
   *
   * @param string $pName
   * @return Client 
   */
  public function setCookieName($pName) {
    $this->mCookieName = basename($pName);
    
    return $this;
  }
  
  /**
   * Creates and initialize CURL resource
   *
   * @param string $pUrl query address
   * @return resource
   */
  protected function initCurl($pUrl) {
    $curl = curl_init($this->buildUrl($pUrl));
    
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->getCookiesPath());
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->getCookiesPath());
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    
    if(true === $this->mDebug) {
      $file_name = strtolower(str_replace('\\', '_', get_class($this)));
      $this->mDebugFileHandler = fopen('curl_'.$file_name.'.log', 'w');
      
      curl_setopt($curl, CURLOPT_VERBOSE, 1);
      curl_setopt($curl, CURLOPT_STDERR, $this->mDebugFileHandler);
    }
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $this->mHeaders);

    return $curl;
  }
  
  /**
   * Builds full URL to API
   *
   * @param string $pUrl
   * @return string
   */
  protected function buildUrl($pUrl) {
    
    if('/' !== substr($pUrl, 0, 1)) {
      $pUrl = '/'.$pUrl;
    }
    
    return $this->mHost.$pUrl;
  }
  
  /**
   * Sends GET request
   *
   * @param string $pUrl resource address (without host name)
   * @param array $pArgs arguments which will be passed to request
   * @return mixed 
   */
  public function get($pUrl, array $pArgs = null) {
    
    if(null !== $pArgs) {
      $pUrl .= '?'.http_build_query($pArgs);
    }
    
    $curl = $this->initCurl($pUrl);

    return $this->request($curl);
  }
  
  /**
   * Sends POST request
   *
   * @param string $pUrl
   * @param string|array $pArgs
   * @return mixed 
   */
  public function post($pUrl, $pArgs) {
    $curl = $this->initCurl($pUrl);
    
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $pArgs);
    
    return $this->request($curl);
  }
  
  /**
   * SEnds PUT request
   *
   * @param string $pUrl
   * @param array $pArgs
   * @return mixed
   */
  public function put($pUrl, $pArgs) {
    $curl = $this->initCurl($pUrl);
    
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $pArgs);
    
    return $this->request($curl);
  }
  
  /**
   * Sends DELETE request
   *
   * @param string $pUrl
   * @param array $pArgs
   * @return mixed
   */
  public function delete($pUrl, array $pArgs = null) {
    $curl = $this->initCurl($pUrl);
    
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
    
    if(null !== $pArgs) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $delArgs);
    }
    
    return $this->request($curl);
  }
  
  /**
   * Executes CURL
   * 
   * This method also is parsing the response headers and returns received content.
   * If some headers indicate an error (e.g. http code 401) an Exception will be throwed.
   *
   * @throws LogicException on codes 401 (Unauthorized), 404 (Resource not found)
   * @throws RuntimeException on codes 500, 403
   * @throws InvalidArgumentException on code 400
   * @param resource $pCurl
   * @return mixed
   */
  protected function request($pCurl) {
		$response = curl_exec($pCurl);
    
    if(false === $response) {
      throw new RuntimeException('CURL Error: '.  curl_error($pCurl), curl_errno($pCurl));
    }
    
    $header_size = curl_getinfo($pCurl, CURLINFO_HEADER_SIZE);
		$headers     = substr($response, 0, $header_size - 4);
		$body        = substr($response, $header_size);
		
		curl_close($pCurl);
    
    $parsed_body = Client\Response::process($headers, $body);
    
    if(null !== $this->mDebugFileHandler) {
      fclose($this->mDebugFileHandler);
      $this->mDebugFileHandler = null;
    }
    
    return $parsed_body;
  }
  
}
