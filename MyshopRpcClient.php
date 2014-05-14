<?php
/**
 * @(#) MyshopRpcClient.php 08/01/2013
 *
 * Copyright 1999-2014(c) MijnWinkel B.V. Rijnegomlaan 33, Aerdenhout,
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
 * Class MyshopRpcClient - partner edition
 *
 * Implements RPC functions for partners
 *
 * Version: 1.0
 * Author: Sem van der Wal
 **/

class MyshopRpcClient {

    private $baseUrl = 'https://www.mijnwinkel.nl/RPC/';
    private $partnerName = null;
    private $partnerKey = null;

    /**
     * Constructor - use the partner name and key which were provided by myshop.com
     * @param String $name - The partner name to use for the RPC requests
     * @param String $key  - The partner key to use for the RPC requests
     */
    function __construct($name, $key){
        $this->partnerName = $name;
        $this->partnerKey = $key;
    }

    /**
     * Gets a Rpc Id from the RPC server
     * @param  String $shopId - The shopnumber of the current shop
     *
     * @return String | NULL - The rpc id or null if the request was unsuccessfull
     */
    private function getRpcId($shopId){
        $url = $this->baseUrl.'id?shop='.$shopId;
        $response = file_get_contents($url);

        if($response && $response!=''){
            // Got a response, now find and return the rpc_id
            $doc = new DOMDocument();
            $doc->loadXML($response);
            $rpcId = $doc->documentElement->firstChild->nodeValue;
            return $rpcId;
        }else{
            return null;
        }
    }

    /**
     * Gets response for a RPC command
     *
     * @param String   $command - The command / path to execute
     * @param String[] $args    - Associative array containing the variables to send
     *
     * @return String - The RPC response
     */
    private function getContents($command, $args){
        $url = $this->baseUrl.$command.'?'.http_build_query($args, '', '&');
        $result = file_get_contents($url);
        return $result;
    }

    /**
     * Get shop information
     * @param String|Int $shopId - The id of the shop of which to get the information
     *
     * @return String - The server response which is XML with shop information when successful
     */
    public function shopInfo($shopId){
        $command = "shop/get/somename.xml";
        $module = "shop_info";
        $rpcId = $this->getRpcId($shopId);
        $args = array(
                        "pk"        =>      sha1($shopId . $this->partnerKey . $this->partnerName . $module . $rpcId),
                        "shop"      =>      $shopId,
                        "rpc_id"    =>      $rpcId,
                        "partner"   =>      $this->partnerName,
                        "module"    =>      "shop_info"
        );

        return $this->getContents($command, $args);
    }

}