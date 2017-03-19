<?php

namespace floreean\XmlQmLaravel;

use fXmlRpc\Client;
use fXmlRpc\Parser\NativeParser;
use fXmlRpc\Parser\XmlReaderParser;
use fXmlRpc\Serializer\NativeSerializer;
use fXmlRpc\Transport\Recorder;
use fXmlRpc\Value\Base64;
use Http\Message\MessageFactory;
use Illuminate\Session\Store;
use League\Flysystem\Exception;
use Illuminate\Contracts\Session\Session;

/**
 * QueueManager class.
 *
 * @class           QueueManager
 * @author          Florian Irlesberger <irlesberger@gmail.com>
 * @date            2017-03-11
 * @version         1.0.0
 *
 * @history
 *
 *
 */
class QueueManager
{
    /* fXmlRpc\Client */
    protected $_client = null;

    /* fXmlRpc\Transport\Recorder */
    protected $_recorder = null;

    /* \fXmlRpc\Parser\XmlReaderParser */
    protected $_parser = null;

    /* Auth token */
    protected $_token = null;

    /* Debug mode */
    protected $_debug = false;

    /** Request timeout */
    protected $_timeout = 600;

    /** Request timeout */
    protected $_interval = 1;

    /** Session Object */
    protected $_session = null;

    public function __construct($config = [], Session $session = null)
    {
        // You may comment this line if you application doesn't support the config
        if (empty($config)) {
            throw new \RunTimeException('QueueManager Facade configuration is empty. Please run `php artisan vendor:publish`');
        }

        $this->_parser = new \fXmlRpc\Parser\XmlReaderParser();

        // no client already set,
        if($this->_client == null){
            $httpClient = new \GuzzleHttp\Client();
            $transport = new \fXmlRpc\Transport\HttpAdapterTransport(
                new \Http\Message\MessageFactory\DiactorosMessageFactory(),
                new \Http\Adapter\Guzzle6\Client($httpClient)
            );

            $this->_recorder = new \fXmlRpc\Transport\Recorder($transport);
            $this->_client = new \fXmlRpc\Client(
                'http://'.$config['xmlrpcUrl'].':'.$config['xmlrpcPort'],
                $this->_recorder,
                new \fXmlRpc\Parser\XmlReaderParser(),
                new \fXmlRpc\Serializer\XmlWriterSerializer()
            );
        }

        $this->_timeout = isset($config['xmlrpcTimeout']) ? $config['xmlrpcTimeout'] : $this->_timeout;
        $this->_interval = isset($config['xmlrpcInterval']) ? $config['xmlrpcInterval'] : $this->_interval;
        $this->_session = $session;
    }

    public function getToken(){

        // first check if there is already a token in the session
        if($sessionToken = $this->_getSessionToken()){
            return $sessionToken;
        }

        try {
            // call method to get the token
            $this->_client->call('GetToken', ['PORTFOLIOOFFICE', '82port041off']);

        } catch(\Exception $e){

            $errCode = $e->getFaultCode();
            if(in_array($errCode, [702, 712, 711])){
                // in some cases try again
                usleep(500000);
                return $this->getToken();
            }

            // in some cases just return false
            return false;
        }

        // no error - save token to session and return it as well
        $this->_token = $this->_parser->parse($this->_recorder->getLastResponse());
        $this->_storeToken($this->_token);
        return $this->_token;
    }

    public function request($request, $webService, $erTitle){
        $resultlist = array ();

        // TODO:: Cleanup following block
        if (count($request) == 2){
            $requestData = $this->_wrapRequest($request[0], $request[1]);
        } elseif (count($request) == 1){
            $requestData = $this->_wrapRequest($request[0]);
        } else {
            throw new QueueManagerException('Malformed XML');
        }

        try {
            $ticket = $this->_doRequest($webService, $requestData);
            $response = $this->_getResponse($ticket);
        } catch (\Exception $e){
            //TODO: do something more useful here
            return $e;
        }

        $toArrayParser = new \Nathanmac\Utilities\Parser\Parser();
        $response = $toArrayParser->xml($response);

        if($response){
            $status = (int) $response['CommunicationResponse']['Result'];
            if($status < 10){
                //Succesfull
                return $response['ResponseData'];
            } else {
                throw new QueueManagerException('XML_Response_Error', $status);
            }
        } else {
            throw new QueueManagerException('XML_Parse_Error');
        }
    }

    private function _doRequest($method, $params){
        if(!$this->_token){
            $this->_token = $this->getToken();
        }

        $request = [
            ['Token' => $this->_token],
            Base64::serialize($params)
        ];

        try {
            $this->_client->call($method, $request);
        } catch (\Exception $e) {
            $errCode = $e->getFaultCode();
            if (in_array($errCode, [712, 711])) {
                return $this->_doRequest($method, $params);
            } elseif (in_array($errCode, [710, 701])){
                usleep(500000);
                return $this->_doRequest($method, $params);
            } else {
                // in some cases just return false
                return false;
            }
        }

        $ticket = $this->_parser->parse($this->_recorder->getLastResponse());
        return $ticket;
    }

    private function _getResponse($ticket = null, $interval = null, $timeout = null) {
        if(!is_null($interval)){
            $this->_interval = $interval;
        }

        if(!is_null($timeout)){
            $this->_timeout = $timeout;
        }

        $request = [
            ['Token' => $this->_token],
            ['Ticket' => $ticket]
        ];

        $i = 1;
        while ($i < $this->_timeout) {
            try {
                $this->_client->call('GetJobResult', $request);
            } catch (\Exception $e) {
                $errCode = (int) $e->getFaultCode();
                if ($errCode == 700) {
                    // not ready yet, try again ...
                    usleep($this->_interval * 500000); // 0.5 second
                    continue;
                } else {
                    // there is another error - stop trying
                    //TODO: throw with error message
                    throw new QueueManagerException('Error', $errCode);
                }
                $i++;
            }

            return $this->_parser->parse($this->_recorder->getLastResponse())->getDecoded();

        }
        throw new Exception("Timeout Exception",450);
    }

    /**
     * TODO: From old system -> Refactor - do i really need this?
     *
     * @param $requestxml
     * @param null $request
     * @return string
     */
    private function _wrapRequest($requestxml, $request=null) {
        if($request != null){
            $tag='<Request>'.$request.'</Request>';
        } else {
            $tag='';
        }
        $data = '<?xml version="1.0" encoding="UTF-8" ?><ServiceContent>'.$tag.'<RequestData>' . $requestxml . '</RequestData></ServiceContent>';
        return $data;
    }

    /**
     * Stores a token in session to use it later without calling the API
     * again
     * @param $token
     * @return bool
     */
    private function _storeToken($token){
        $this->_session->put('xmlrpc_token', $token);
    }

    /**
     * @return bool
     */
    private function _getSessionToken(){
        if($this->_session->get('xmlrpc_token') != ''){
            return $this->_session->get('xmlrpc_token');
        }
        return false;
    }
}


/*
|--------------------------------------------------------------------------
| QueueManager Exceptions
|--------------------------------------------------------------------------
|
| These exceptions classes are used in this file. Feel free to add your
| custom feedback and classes.
|
*/

class QueueManagerException extends \Exception
{
}