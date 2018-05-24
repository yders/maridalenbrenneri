<?php
/*
 * Wrapper for Logistra Cargonizer API
 * w\ general array to xml converter
 * + xml writer class
 *
 * Copyright (C) 2011 by ServiceLogistikk AS
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 */
/**
 * @param string api_key
 * @param int sender_id
 * @param string url [optional]
 */
class cargonizer {
	private $consignment_url = "http://cargonizer.no/consignments.xml";
	private $transport_agreement_url = "http://cargonizer.no/transport_agreements.xml";
	private $api_key;
	private $sender_id;
	private $curl; 
	private $data_xml = "<xml></xml>";
	private $data = array();
	private $pkg_number;
	private $urls = array();
	private $cost_estimate = 0;
	private $error = array();
	private $error_flag = 0;
	private $sxml;
	
	public function __construct($api_key,$sender_id,$url = "") {
		if($url != '') $this->consignment_url = $url;
		$this->api_key = $api_key;
		$this->sender_id = $sender_id;
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_URL, $this->consignment_url); 
		curl_setopt($this->curl, CURLOPT_VERBOSE, 1); 
		curl_setopt($this->curl, CURLOPT_HEADER, 0); 
		curl_setopt($this->curl, CURLOPT_POST, 1); 
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1); 
	}
	
	public function __destruct() {
		curl_close($this->curl);
	}
	
	public function getPkgNumber() {
		return $this->pkg_number;
	}
	
	/**
	 * 
	 * Returns an array with the consignment document urls
	 * @return array
	 */
	public function getUrls() {
		return $this->urls;
	}
	
	public function getErrorFlag() {
		return $this->error_flag;
	}
	
	public function getErrors() {
		return $this->errors;
	}
	
	public function getCostEstimate() {
		return $this->cost_estimate;
	}
	
	/**
	 * 
	 * returns the resulting xml response from cargonizer consignment call
	 * @return simplexml_object
	 */
	public function getResultXml() {
		return $this->sxml;
	}
	
	/**
	 * 
	 * Creates a consignment
	 * @param array $data
	 * @param int $debug [0|1] [optional]
	 */
	public function requestConsignment($data,$debug=0) {
		$this->pkg_number = "0";
		$this->urls = array();
		$this->cost_estimate = 0;
		$this->data = $data;
				
		$xw = &new CRG_Xmlwriter();
		$xw = $this->parseArray($data,$xw);
		$xml = $xw->getXml();
		curl_setopt($this->curl, CURLOPT_URL, $this->consignment_url);
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");
		
		if($debug == 1) echo "XML<br>\n".print_r($xml,1)."<br>\n";
		
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $xml);
		$headers = array(
			"X-Cargonizer-Key:".$this->api_key,
			"X-Cargonizer-Sender:".$this->sender_id,
			"Content-type:application/xml",
			"Content-length:".strlen($xml),
		);
		if($debug == 1) echo "Header\n".print_r($headers,1)."<br>\n";	
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers); 
		
		if($debug == 0) $response = $this->runRequest($debug);
		
		if($debug == 0) $this->parseResponse($response,$debug);
		
		return $response;
	}
	/**
	 * 
	 * Fetches the transport agreements for the set API key and Sender ID
	 * You need the transport ID and Product for the consignment call
	 * @param string $url [optional]
	 * @return simplexml_object
	 */
	public function requestTransportAgreements($url = "") {
		if($url == '') $url = $this->transport_agreement_url;
		echo "URL: $url<br>\n";
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_POST, 0);
		$headers = array(
			"X-Cargonizer-Key:".$this->api_key,
			"X-Cargonizer-Sender:".$this->sender_id,
			"Content-type:application/xml",
			"Content-length:0",
		);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		$response = $this->runRequest($debug);
		
		return $response;
	}
	
	/**
	 * General array to xml parser
	 */
	private function parseArray($data,&$xw) {
		foreach($data as $k=>$v) {
			if($k == "_attribs" and !is_numeric($k)) {
				continue;
			}
			if(is_numeric($k)) {
				$xw = $this->parseArray($v,$xw);
			} else if(is_array($v)) {
				if(count($v) == 1 and count($v['_attribs']) > 0) {
					$xw->element($k,'',$v['_attribs']);
				} else {
					$xw->push($k,$v['_attribs']);
					$xw = $this->parseArray($v,$xw);
					$xw->pop();
				}
			} else {
				$xw->element($k,$v);
			}
		}
		return $xw;
	}
	
	private function runRequest($debug=0) {
		$response = curl_exec($this->curl); 
		if(!curl_errno($this->curl)) { 
			$info = curl_getinfo($this->curl); 
			if($debug == 1) echo 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']."<br>\n"; 
		} else { 
			if($debug == 1) echo 'Curl error: ' . curl_error($this->curl)."<br>\n";
			$this->error_flag = 1;
			$this->errors['curl_request'] .= curl_error($this->curl)."\n";
		} 
		return $response;
	}
	
	private function parseResponse($xml,$debug=0) {
		$sxml = simplexml_load_string($xml);
		$this->sxml = $sxml;
		
		if($sxml->getName() == "errors") {
			if($debug == 1) echo "SXML<br><pre>".print_r($sxml,1)."</pre>";
			$this->error_flag = 1;
			$this->errors['parsing'] .= $sxml."\n".print_r($this->data,1);
		} else {
			if($debug == 1) echo "SXML<br><pre>".print_r($sxml,1)."</pre>";
		}
		foreach($sxml->consignment as $consignment) {
			$this->pkg_number = (string)$consignment->{'number-with-checksum'};
			if($debug == 1) echo "PDF: ".$consignment->{'consignment-pdf'}."<br>\n";
			$this->urls['consignment-pdf'] = $consignment->{'consignment-pdf'};
			$this->urls['collection-pdf'] = (string)$consignment->{'collection-pdf'};
			$this->urls['waybill-pdf'] = (string)$consignment->{'waybill-pdf'};
			$this->urls['tracking-url'] = (string)$consignment->{'tracking-url'};
			if($debug == 1) echo "Values: ".print_r((string)$consignment->{'cost-estimate'}->gross,1)."<br>\n";
			$this->cost_estimate = (string)$consignment->{'cost-estimate'}->gross;
		}
	}
}
/*
 * Modified version of Xmlwriter
 * ServiceLogistikk AS
 * 
 * Modified from
 * Simon Willison, 16th April 2003
 * Based on Lars Marius Garshol's Python XMLWriter class
 * See http://www.xml.com/pub/a/2003/04/09/py-xml.html
 * 
 */
class CRG_Xmlwriter {
    private $xml;
    private $indent;
    private $stack = array();
    function CRG_Xmlwriter($indent = '  ',$encoding = 'UTF-8') {
        $this->indent = $indent;
        $this->xml = "<?xml version=\"1.0\" encoding=\"$encoding\"?>"."\n";
    }
    function _indent() {
        for ($i = 0, $j = count($this->stack); $i < $j; $i++) {
            $this->xml .= $this->indent;
        }
    }
    //* Push
    function push($element, $attributes = array(), $ns = "") {
        $this->_indent();
        $this->xml .= '<'.$element;
        if($ns != '') $this->xml .= " ".$ns; 
        foreach ($attributes as $key => $value) {
            $this->xml .= ' '.$key.'="'.$value.'"';
        }
        $this->xml .= ">\n";
        $this->stack[] = $element;
    }
    function push_cdata($element, $attributes = array()) {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
            $this->xml .= ' '.$key.'="<![CDATA['.$value.']]>"';
        }
        $this->xml .= ">\n";
        $this->stack[] = $element;
    }
    function push_htmlentities($element, $attributes = array()) {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
            $this->xml .= ' '.$key.'="'.htmlentities($value).'"';
        }
        $this->xml .= ">\n";
        $this->stack[] = $element;
    }
    //* Element
    function element($element, $content = '', $attributes = array(), $ns = '', $nil = '') {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
            $this->xml .= ' '.$key.'="'.$value.'"';
        }
        if($content == '') {
        	if($nil != '') $this->xml .= " ".$nil;
        	if($ns != '') $this->xml .= " ".$ns;
        	$this->xml .= " />\n";
        } else {
        	if($ns != '') $this->xml .= " ".$ns;
        	$this->xml .= '>'.$content.'</'.$element.'>'."\n";
        }
    }
    function element_cdata($element, $content = '', $attributes = array(), $length = 0) {
    	
    	if($length > 0) {
	    	$c_len = strlen("![CDATA[]]");
	    	if(strlen($content)+$c_len > $length) {
	    		$real_length = $length-$c_len;
	    		$content = substr($content,0,$real_length);
	    	}
    	}
    	
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
            $this->xml .= ' '.$key.'="<![CDATA['.$value.']]>"';
        }
        if($content == '') {
        	$this->xml .= " />\n";
        } else {
        	$this->xml .= '><![CDATA['.$content.']]></'.$element.'>'."\n";
        }
    }
    function element_htmlentities($element, $content = '', $attributes = array()) {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
            $this->xml .= ' '.$key.'="'.htmlentities($value).'"';
        }
        if($content == '') {
        	$this->xml .= " />\n";
        } else {
        	$this->xml .= '>'.htmlentities($content).'</'.$element.'>'."\n";
        }
    } 
    function pop() {
        $element = array_pop($this->stack);
        $this->_indent();
        $this->xml .= "</$element>\n";
    }
    function getXml() {
        return $this->xml;
    }
}
?>