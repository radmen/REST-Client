<?php

namespace Rest\Client;

class Response {

  private $mHeaders;

  private $mBody;

  private function __construct($pBody) {
    $this->mBody = $pBody;
  }

  /**
   * Analyse response headers, and return parsed content
   *
   * Based on headers method will try to decode the content
   * (e.g. header has 'content-type: text/json' so the conent will be JSON-decoded).
   *
   * If there're some errors (HTTP codes 40x) an Exception will be thrown (@see self::checkHttpCode())
   *
   * @param string $pHeaders response headers
   * @param string $pBody response content
   * @return mixed parsed response content
   */
  public static function process($pHeaders, $pBody) {
    $class = get_called_class();
    $response = new $class($pBody);
    $response->parseHeaders($pHeaders);

    return $response->get();
  }

  /**
   * Returns parsed content
   *
   * If the content is in right format it will be decoded, and then returned
   * (e.g. JSON string will be returned as an array).
   *
   * @return mixed
   */
  public function get() {

    if(true == preg_match('#json#i', $this->mHeaders['Content-Type'])) {
      return json_decode($this->mBody, true);
    }

    return $this->mBody;
  }

  /**
   * Checks headers for an error
   *
   * Base on different HTTP codes specific errors will be called.
   *
   * @throws InvalidArgumentException for http code - 400
   * @throws RuntimeException wher api key is invalid, or on internal server errors
   * @throws LogicException for 404 errors
   * @param string $pHeaders
   * @param string $pResponseBody
   */
  protected function checkHttpCode($pHeaders) {
    $status_code = array();
    preg_match_all('/HTTP\/\d\.\d\s+(\d{3})/', $pHeaders, $status_code, PREG_SET_ORDER);

    $last_match = array_pop($status_code);

    switch($last_match[1]) {

      case 200:
      case 201:
      case 204:
        break;

      case 400:
        throw new InvalidArgumentException($this->mBody, 400);

      case 401:
        throw new LogicException('Unauthorized');

      case 403:
        throw new RuntimeException('API key was invalid.', 403);

      case 404:
        throw new LogicException('Resource not found', 404);

      case 500:
        throw new RuntimeException('Internal server error', 500);
    }
  }

  /**
   * Converts headers to array list
   *
   * @param string $pHeaders
   * @return array
   */
  protected function parseHeaders($pHeaders) {
    $this->checkHttpCode($pHeaders);

    if(true === empty($pHeaders)) {
      return array();
    }

    $tmp = array_map('trim', explode("\n", $pHeaders));
    $this->mHeaders = array();

    foreach($tmp as $line) {

      if(false == preg_match('#^([a-z-]+?):\s*(.+)$#i', $line, $match)) {
        continue;
      }

      $this->mHeaders[$match[1]] = $match[2];
    }
  }
}
