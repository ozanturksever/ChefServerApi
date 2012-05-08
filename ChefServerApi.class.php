<?php

/**
 * Chef Server Api Class
 *
 * @uses openssl
 *
 */
class ChefServer {

  private $con;
  private $header;
  private $signVersion = 'version=1.0';
  private $chefVersion = '0.10.8';
  private $userAgent = 'php chef client 0.0.1';
  
  /**
   * Constructor Class.
   * @param string $host
   * @param int $port
   * @param string $userid // Crowbar Client Id
   * @param string $keyfile // Crowbar Access Key File
   */
  public function __construct($host, $port, $userid, $keyfile='/etc/chef/client.key') {
    $this->con = curl_init();
    $this->host = $host;
    $this->port = $port;
    $this->url = 'http://'.$host.":".$port;
    $this->userid = $userid;
    $this->key = $this->getKey($keyfile);
    curl_setopt($this->con, CURLOPT_HEADER, 0);
    curl_setopt($this->con, CURLOPT_VERBOSE, 0);
    curl_setopt($this->con, CURLOPT_RETURNTRANSFER, 1);
  }
  
  private function getKey($file) {
    return openssl_get_privatekey("file://".$file);
  }
  
  private function signCanonicalHeaders($method, $path, $content) {
    $content_hash = $this->getContentHash($content);
    $path_hash = base64_encode(sha1($path,true));
    $time = gmdate("Y-m-d\TH:i:s\Z");
    $content="Method:${method}\nHashed Path:${path_hash}\nX-Ops-Content-Hash:${content_hash}\nX-Ops-Timestamp:${time}\nX-Ops-UserId:".$this->userid;
    openssl_private_encrypt($content, $crypted, $this->key);
    return $crypted;
  }
  
  private function getAuthorizationHeaders($signedContent) {
    //$sigs = split("\n", chunk_split(base64_encode($signedContent), 60));
    $sigs = explode("\n", chunk_split(base64_encode($signedContent), 60));
    for($i=0;$i<count($sigs);$i++) {
	$h[] = "X-Ops-Authorization-".($i+1).": ".trim($sigs[$i]);
    }
    return $h;
  }
  
  private function getContentHash($content) {
    return base64_encode(sha1($content,true));
  }
  
  private function getTime() {
    return $this->_time;
  }
  private function setTime() {
    $this->_time = gmdate("Y-m-d\TH:i:s\Z");
  }
  
  private function getUrl() {
    return $this->url;
  }
    
  private function getHeaders($method, $uri, $content='') {
	$aHeaders = $this->getAuthorizationHeaders($this->signCanonicalHeaders($method,$uri, $content)) ;
	$h[] = "X-Ops-UserId: ".$this->userid;
	$h[] = "X-Ops-Sign: ".$this->signVersion;
	$h[] = "X-Ops-Content-Hash: ".$this->getContentHash($content);
	$h[] = "X-Ops-Timestamp: ".$this->getTime();
	//$h[] = "Host: ".$this->getUrl();
	$h[] = "Accept: application/json";
	$h[] = "X-Chef-Version: ".$this->chefVersion;
	$h[] = "User-Agent: ".$this->userAgent;
	$h[] = "Content-Type: application/json";
	$h[] = "Connection: close";
    return array_merge($aHeaders, $h);
  }
  private function setDst($uri) {
    curl_setopt($this->con, CURLOPT_URL, $this->url.$uri);
  }
  
  private function prepare($uri, $method='GET', $encodedData) {
    $this->setDst($uri);
    $this->setTime();
    if ($method == 'POST') {
	curl_setopt($this->con, CURLOPT_POST, true);
	curl_setopt($this->con, CURLOPT_POSTFIELDS, $encodedData);
	curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'POST');
	//var_dump($encodedData);
    } else if ($method == 'PUT') {
	curl_setopt($this->con, CURLOPT_POST, true);
	curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($this->con, CURLOPT_POSTFIELDS, $encodedData);
    } else if ($method == 'DELETE') {
	curl_setopt($this->con, CURLOPT_POST, false);
	curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } else {
	curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($this->con, CURLOPT_POST, false);
	curl_setopt($this->con, CURLOPT_HTTPGET, true);
    }
    
  }

  private function execute($headers) {
    curl_setopt($this->con, CURLOPT_HTTPHEADER, $headers);
    return curl_exec($this->con);
  }
  
  public function get($uri) {
//  	echo "get fonksiyonu : ".$uri."<br>";
    $this->prepare($uri, 'GET', '');
    $headers = $this->getHeaders('GET', $uri);
    return (object) json_decode($this->execute($headers));
  }
  
  public function post($uri, $data) {
    $encodedData = json_encode($data);
    $this->prepare($uri, 'POST', $encodedData);
    $headers = $this->getHeaders('POST', $uri, $encodedData);
    return (object) json_decode($this->execute($headers));
  }

  public function put($uri, $id, $data) {
//  	echo "put fonksiyonu : ".$uri."- id: $id - <br>";
    $encodedData = json_encode($data);
    $path = $uri.'/'.$id;
    $this->prepare($path, 'PUT', $encodedData);
    $headers = $this->getHeaders('PUT', $path, $encodedData);
    return (object) json_decode($this->execute($headers));
  }

  public function delete($uri, $id) {
    $path = $uri.'/'.$id;
    $this->prepare($path, 'DELETE', $encodedData);
    $headers = $this->getHeaders('DELETE', $path, $encodedData);
    return (object) json_decode($this->execute($headers));
  }
  
}
//Example
//$api = new ChefServer("192.168.124.10",4000,'webserver');
//echo "<pre>";
//var_dump( $api->get('/clients') );
//var_dump( $api->get('/data/test') );
//var_dump( $api->post('/data', array('name'=>'test')) );
//var_dump( $api->post('/data/test', array('id'=>'bla','hedele'=>'test')) );
//var_dump( $api->put('/data/test', 'bla', array('id'=>'bla','hedele'=>'test', 'ops'=>'hede')) );
//var_dump( $api->delete('/data/test', 'bla') );
