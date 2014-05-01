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
	 * helloWorld.php
	 * 
	 * Shows example usage of myshopRequest class.
	 *
	 * Version: 1.0
	 * Author: Sem van der Wal
	 **/

	include 'myshopRequest.php';
	
	/* Set your private key for this plugin here */
	$privateKey = '';
	
	/* Set base location / path for this plugin */
	$baseLocation = '/connections/myaccount/';
	
	/* Base string for the body */
	$body = '';
	
	try{
		/* Create MyshopRequest instance */
		$myshopRequest = new MyshopRequest($privateKey);
		
		/* Get state variables */
		$stateVariables = $myshopRequest->getStates(); // Returns associative array with all state variables
		
		/* Get plugin parameters */
		$pluginParameters = $myshopRequest->getParams(); // Returns associative array with all plugin parameters
		
		/* Get location */
		$location = $myshopRequest->getLocation();
		if($baseLocation.'start.html'==$location){
			/* Return start screen */
			$body .= '<h1>helloWorld!</h1>';
			$body .= '<p>Press next to continue</p>';
		}else if($baseLocation.'settings.html'==$location){
			/* Return settings screen */
			$body .= '<label for="myName">Please enter your name:</label><br/>';
			$body .= '<input type="text" name="myName"/>';
		}else if($baseLocation.'submit.html'==$location){
			/* Process user input and return submit screen */
			$name = $pluginParameters['myName']; // Alternatively we could've used $myshopResource->getParameter('myName')
			$body .= '<p>';
			$body .= 'Thank you for your cooperation, your name is:'.$name.'<br/><br/>';
			$body .= 'This is the end of this demo.';
			$body .= '</p>';
		}else{
			/* Unknown location / path - do not process */
			error_log('Illegal location detected: '.$location);
		}
	}catch(MyshopEmptyRequestBodyException $e){
		/* Request body was empty */
		error_log('Unable to process request:'.$e->getMessage());
	}catch(MyshopXMLException $e){
		/* XML did not have expected format */
		error_log('Unable to process request:'.$e->getMessage());
	}catch(MyshopSignatureException $e){
		/* Signature was incorrect */
		error_log('Unable to process request:'.$e->getMessage());
	}

	echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
?>
<response>
	<body>
		<![CDATA[
			<?php echo $body; ?>
		]]>
	</body>
</response>