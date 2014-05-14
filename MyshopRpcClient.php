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
    private $tmpDirectory = null;

    /**
     * Constructor - use the partner name and key which were provided by myshop.com
     *
     * @param String $name      - The partner name to use for the RPC requests
     * @param String $key       - The partner key to use for the RPC requests
     * @param String $tmpDir    - The path to a temporary directory to use for storing temporary files
     */
    function __construct($name, $key, $tmpDir=null){
        $this->partnerName = $name;
        $this->partnerKey = $key;
        if($tmpDir!=null && is_dir($tmpDir)){
            $this->tmpDirectory = $tmpDir;
        }else{
            $this->tmpDirectory = realpath('./tmp');
            if(!is_dir($this->tmpDirectory)) mkdir($this->tmpDirectory);
        }
    }

    /**
     * Gets a Rpc Id from the RPC server
     *
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
     * Upload a file to the server
     *
     * @param String    $command    - The command / path to execute
     * @param String[]  $args       - Associative array containing the variables to send
     * @param String    $name       - The name of the file to send
     * @param String    $data       - The data of the file to send
     *
     * @return mixed
     */
    private function uploadFile($command, $args, $name, $data){

        $url = $this->baseUrl.$command.'?'.http_build_query($args);
        $files = array(
            "userfile" => array(
                'name' => $name,
                'type' => 'application/zip',
                'content' => $data
            )
        );
        return $this->post($url, null, $files);

    }

    /**
     * Posts files to the given url
     *
     * @param String    $url    - The url to post the data to
     * @param String[]  $args   - Associative array containing the variables to send
     * @param Array     $files  - The files to send, keys are used as field names (mimicking a form-data post)
     *                            each value is an array containing file specific information:
     *                            - String name : The filename of the file
     *                            - String type : The content type of the file (for instance application/zip)
     *                            - Mixed  data : The data of the file to send - data is send in plain format, without encoding
     *
     * @return mixed|string     - The server response or error message in case of an error
     */
    public function post($url, $args, $files){

        $delimiter = '-------------' . uniqid();

        $data = '';

        // populate normal fields first (simpler)
        if($args!=null){
            foreach ($args as $name => $content) {
                $data .= "--" . $delimiter . "\r\n";
                $data .= 'Content-Disposition: form-data; name="' . $name . '"';
                // note: double endline
                $data .= $content;
                $data .= "\r\n\r\n";
            }
        }
        // populate file fields
        foreach ($files as $name => $file) {
            $data .= "--" . $delimiter . "\r\n";
            // "filename" attribute is not essential; server-side scripts may use it
            $data .= 'Content-Disposition: form-data; name="' . $name . '";' .
                ' filename="' . $file['name'] . '"' . "\r\n";
            // this is, again, informative only; good practice to include though
            $data .= 'Content-Type: ' . $file['type'] . "\r\n";
            // this endline must be here to indicate end of headers
            $data .= "\r\n";
            // the file itself (note: there's no encoding of any kind)
            $data .= $file['content'] . "\r\n";
        }
        // last delimiter
        $data .= "--" . $delimiter . "--\r\n";

        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER , array(
            'Content-Type: multipart/form-data; boundary=' . $delimiter,
            'Content-Length: ' . strlen($data)));
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($handle);
        if($result!=false){
            return $result;
        }else{
            return curl_error($handle);
        }
    }

    /**
     * Get shop information
     *
     * @param String|Int    $shopId     - The id of the shop of which to get the information
     *
     * @return String                   - The server response which is XML with shop information when successful
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

    /**
     * Gets a productlist from RPC.
     *
     * @param String        $shopId     - The shopnumber of the shop to get the productlist from
     * @param Integer       $cid        - The productlist id
     *
     * @return String|Bool              - A XML string containing the productlist data or false on error
     */
    public function getProductlist($shopId, $cid){

        $command = 'productlist/get/';
        $module = 'productlist_get';
        $rpcId = $this->getRpcId($shopId);
        $args = array(
            'pk' => sha1($shopId.$cid.$this->partnerKey.$this->partnerName.$module.$rpcId),
            'productlist' => $cid,
            'shop' => $shopId,
            'rpc_id' => $rpcId,
            'partner' => $this->partnerName,
            'module' => $module
        );

        $zipContents = $this->getContents($command, $args);
        $tmpZip = fopen($this->tmpDirectory.'tmp.zip', 'w');
        if($tmpZip!=false){
            fwrite($tmpZip, $zipContents);
            fclose($tmpZip);
        }

        $zip = new ZipArchive();
        if($zip->open($this->tmpDirectory.'tmp.zip')==true){
            $xml = $zip->getFromIndex(0);
            if(empty($xml)){
                error_log("No zip contents: \n".file_get_contents($this->tmpDirectory.'tmp.zip'));
            }
            $zip->close();
            unlink($this->tmpDirectory.'tmp.zip');

            return $xml;
        }else{
            return false;
        }
    }

    /**
     * Gets productlist info from RPC.
     *
     * @param String    $shopId     - The shopnumber of the shop to get the productlist from
     * @param Integer   $cid        - The productlist id
     *
     * @return String               - A XML string containing the productlist meta data
     */
    public function getProductlistInfo($shopId, $cid){
        $command = 'productlist/info/';
        $module = 'productlist_get';
        $rpcId = $this->getRpcId($shopId);
        $args = array(
            'pk' => sha1($shopId.$cid.$this->partnerKey.$this->partnerName.$module.$rpcId),
            'productlist' => $cid,
            'shop' => $shopId,
            'rpc_id' => $rpcId,
            'partner' => $this->partnerName,
            'module' => $module
        );

        $contents =  $this->getContents($command, $args);
        return $contents;
    }

    /**
     * Uploads a productlist to myshop.com
     *
     * @param String    $shopId         - The shopid to upload the productlist to
     * @param Integer   $cid            - The catalog id of the productlist to upload
     * @param String    $name           - The name of the productlist to upload
     * @param String    $csvdata        - The csv data of the productlist to upload
     *
     * @return bool                     - True if the upload was successfull
     */
    public function uploadProductList($shopId, $cid, $name, $csvdata){

        $command = 'productlist/update';
        $module = 'productlist_update';
        $rpcId = $this->getRpcId($shopId);
        $filename = $name.'.zip';
        $args = array(
            'pk' => sha1($shopId.$cid.$this->partnerKey.$this->partnerName.$module.$filename.$rpcId),
            'productlist' => $cid,
            'shop' => $shopId,
            'filename' => $filename,
            'rpc_id' => $rpcId,
            'partner' => $this->partnerName,
            'module' => $module
        );

        // Prepare zip file to upload
        $zip = new ZipArchive();
        $zip->open($this->tmpDirectory.$filename, ZipArchive::CREATE);
        $zip->addFromString($name.'.csv', $csvdata);
        $zip->close();

        $zipdata = file_get_contents($this->tmpDirectory.$filename);

        $result = $this->uploadFile($command, $args, $filename, $zipdata);

        return strpos($result, "invalid request") === false;
    }

    /**
     * Updates the stock value for a particular product
     *
     * @param String|Integer    $shopId         - The id of the shop to use
     * @param String            $productId      - The id of the product to change the stock value for
     * @param String|Integer    $stockValue     - The stock value to set
     *
     * @return String                           - The server response
     */
    public function updateStock($shopId, $productId, $stockValue){

        $command = 'stock/update_n';
        $module = 'stock_update_n';
        $rpcId = $this->getRpcId($shopId);
        $args = array(
            'pk'            =>  sha1($shopId.$this->partnerKey.$this->partnerName.$module.$productId.$stockValue.$rpcId),
            'shop'          =>  $shopId,
            'productid0'    =>  $productId,
            'stock_value0'  =>  $stockValue,
            'count'         =>  1,
            'rpc_id'        =>  $rpcId,
            'partner'       =>  $this->partnerName,
            'module'        =>  $module
        );

        $contents = $this->getContents($command, $args);

        return $contents;

    }

    /**
     * Gets the xml from the rpc order/list command.
     *
     * @param String    $shopId     - The shop id to get orders from
     * @param String    $changeTime - The change date from which to get changed orders (yyyymmddhhmmss)
     *                                used only for limiting data size, may not be accurate, so build in a check for your own application
     *
     * @return String               - A string containing the order xml of all orders in the shop
     */
    public function getOrderList($shopId, $changeTime='20010101010101'){

        $command = 'orders/list';
        $module = 'orders_get';
        $rpcId = $this->getRpcId($shopId);
        $args = array(
            'pk'            =>  sha1($shopId.$changeTime.$this->partnerKey.$this->partnerName.$module.$rpcId),
            'shop'          =>  $shopId,
            'rpc_id'        =>  $rpcId,
            'partner'       =>  $this->partnerName,
            'module'        =>  $module,
            'change_time'   =>  $changeTime,
            'system_export' =>  '1'
        );

        $contents = $this->getContents($command, $args);

        return $contents;

    }

    /**
     * Gets the xml of a specific order.
     *
     * @param String    $shopId     - The shop id to get orders from
     * @param String    $orderId    - The id of the order to get (shopid_orderid, for instance 1817700_00012)
     *
     * @return String               - A string containing the order xml of all orders in the shop
     */
    public function getOrder($shopId, $orderId){

        $command = 'orders/list';
        $module = 'orders_get';
        $rpcId = $this->getRpcId($shopId);
        $args = array(
            'pk'            =>  sha1($shopId.$orderId.$this->partnerKey.$this->partnerName.$module.$rpcId),
            'shop'          =>  $shopId,
            'order_id'      =>  $orderId,
            'rpc_id'        =>  $rpcId,
            'partner'       =>  $this->partnerName,
            'module'        =>  $module,
            'system_export' =>  '1'
        );

        $contents = $this->getContents($command, $args);

        return $contents;

    }

}