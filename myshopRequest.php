<?php

	/**
	 * @(#) myshopRequest.php 08/01/2013
	 *
	 * Copyright 1999-2013(c) MijnWinkel B.V. Rijnegomlaan 33, Aerdenhout,
	 * North Holland, NL-2114EH, The Netherlands All rights reserved.
	 *
	 * This software is provided "AS IS," without a warranty of any kind. ALL
	 * EXPRESS OR IMPLIED CONDITIONS, REPRESENTATIONS AND WARRANTIES,
	 * INCLUDING ANY IMPLIED WARRANTY OF MERCHANTABILITY, FITNESS FOR A
	 * PARTICULAR PURPOSE OR NON-INFRINGEMENT, ARE HEREBY EXCLUDED. MYSHOP AND
	 * ITS LICENSORS SHALL NOT BE LIABLE FOR ANY DAMAGES OR LIABILITIES
	 * SUFFERED BY LICENSEE AS A RESULT OF  OR RELATING TO USE, MODIFICATION
	 * OR DISTRIBUTION OF THE SOFTWARE OR ITS DERIVATIVES. IN NO EVENT WILL
	 * MYSHOP OR ITS LICENSORS BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA, OR
	 * FOR DIRECT, INDIRECT, SPECIAL, CONSEQUENTIAL, INCIDENTAL OR PUNITIVE
	 * DAMAGES, HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY,
	 * ARISING OUT OF THE USE OF OR INABILITY TO USE SOFTWARE, EVEN IF MYSHOP HAS
	 * BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
	 *
	 * You acknowledge that Software is not designed, licensed or intended
	 * for use in the design, construction, operation or maintenance of any
	 * nuclear facility.
	 *
	 *
	 * Class MyshopRequest
	 * 
	 * Reads the POST Body from the current request and provides access to the state and
	 * parameter variables.
	 *
	 * Version: 1.2
	 * Author: Sem van der Wal
	 **/
	
	class MyshopRequest {

		private $root;
		private $params;
		private $state;
		private $location;
		private $active;
		private $signature;
		private $privateKey;

		function __construct($privateKey){
			if($privateKey && $privateKey!=''){
				$this->privateKey = $privateKey;
				
				$doc = new DOMDocument();
				$rbody = $this->getRequestBody();
				$ok = false;
				if($rbody!=null){
					$ok = $doc->loadXML($rbody);
					if($ok){
						$this->root = $doc->documentElement;
		
						$this->buildState();
						$this->buildParams();
						
						$this->setLocation();
						$this->setActive();
					}else{
						throw new MyshopXMLException();
					}
				}else{
					throw new MyshopEmptyRequestBodyException();
				}
				/* Signature check is not working correctly - temporarily disabled
				if(!$this->checkSignature()){
					throw new MyshopSignatureException();
				}
				*/
			}else{
				throw new InvalidArgumentException('Missing argument privateKey');
			}
		}

		/* Returns all states */
		public function getStates(){
			return $this->state;
		}

		/* Returns all parameters */
		public function getParams(){
			if($this->params){
				return $this->params;
			}else{
				error_log('Empty params');
				return '';
			}
		}

		/* Returns specific named state variable */
		public function getState($name){
			if($this->state){
				return $this->state[$name];
			}else{
				error_log('Empty state');
				return '';
			}
		}

		/* Returns specific named parameter */
		public function getParam($name){
			return $this->params[$name];
		}
		
		/* Returns location of the current plugin call */
		public function getLocation(){
			return $this->location;
		}
		
		/* Returns active state of the current plugin call */
		public function getActive(){
			return $this->active;
		}
		
		/* Checks if the given signature is the same as the expected one */
		private function checkSignature(){
			$signature = sha1($this->getState('application').'|'.$this->getRpcId().'|'.$this->getState('vid').'|'.$this->privateKey);
			return $signature == $this->getSignature();
		}

		/* Build the state array */
		private function buildState(){
			$this->state = array();
			try{
				$stateElements = $this->root->getElementsByTagName('state')->item(0)->childNodes;
				for($i=0;$i<$stateElements->length;$i++){
					$el = $stateElements->item($i);
					$this->state[$el->nodeName] = $el->nodeValue;
				}
			}catch(Exception $e){
				error_log('Encountered error while reading XML: '.$e->getMessage());
			}
		}

		/* Build the params array */
		private function buildParams(){
			$this->params = array();
			try{
				$paramElements = $this->root->getElementsByTagName('request')->item(0)->getElementsByTagName('parameters')->item(0)->childNodes;
				for($i=0;$i<$paramElements->length;$i++){
					$el = $paramElements->item($i);
					$this->params[$el->getAttribute('name')] = $el->nodeValue;
				}
			}catch(Exception $e){
				error_log('Encountered error while reading XML: '.$e->getMessage());
			}
		}
		
		/* Find and set the location of the current request in the myshop backoffice */
		private function setLocation(){
			try{
				$this->location = $this->root->getElementsByTagName('request')->item(0)->getElementsByTagName('location')->item(0)->nodeValue;
			}catch(Exception $e){
				error_log('Encountered error while reading XML: '.$e->getMessage());
			}
		}
		
		/* Find and set the plugin active state */
		private function setActive(){
			try{
				$this->active = $this->root->getElementsByTagName('request')->item(0)->getElementsByTagName('plugin_active')->item(0)->nodeValue == '1';
			}catch(Exception $e){
				error_log('Encountered error while reading XML: '.$e->getMessage());
			}
		}

		/* Returns the rpc id, used to generate the signature */
		private function getRpcId(){
			try{
				return $this->root->getAttribute('rpc_id');
			}catch(Exception $e){
				error_log('Encountered error while reading XML: '.$e->getMessage());
				return '';
			}
		}
		
		/* Returns the signature given by the server */
		private function getSignature(){
			try{
				return $this->root->getAttribute('signature');
			}catch(Exception $e){
				error_log('Encountered error while reading XML: '.$e->getMessage());
				return '';
			}
		}
		
		/* Returns the request body */
		private function getRequestBody(){
			$req_body = '';
			$fh   = @fopen('php://input', 'r');
			if ($fh){
				while (!feof($fh)){
					$s = fread($fh, 1024);
					if (is_string($s)){
						$req_body .= $s;
					}
				}
				fclose($fh);
			}
			return $req_body;
		}

	}

	/* Exception class for when the myshopRequest class is unable to read the xml from the request body */
	class MyshopXMLException extends Exception {
		
		function __construct($msg='Unable to parse XML'){
			parent::__construct($msg);
		}
		
	}
	
	/* Exception class for when the request body has been found empty */
	class MyshopEmptyRequestBodyException extends Exception {
	
		function __construct($msg='Request Body was empty'){
			parent::__construct($msg);
		}
	
	}
	
	/* Exception class for when the signature has been found invalid */
	class MyshopSignatureException extends Exception {
	
		function __construct($msg='Unexpected signature found'){
			parent::__construct($msg);
		}
	
	}

?>