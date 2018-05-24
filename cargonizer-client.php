 <?php 

echo '<h1>Running cargonizer stuff</h1>'; 

$crg_api_key = "3929bc31e5d97fe928ab0c1b3dde7ff71bf85ad2";
$crg_sender_id = "1319";
$crg_consignment_url = "http://sandbox.cargonizer.no/consignments.xml";
$crg_transport_url = "http://sandbox.cargonizer.no/transport_agreements.xml";

$error = array();
$error_flag = 0;

$curl = curl_init();

function runRequest($debug=0) {
    global $curl;

    $response = curl_exec($curl); 
    
    if(!curl_errno($curl)) { 
        $info = curl_getinfo($curl); 
        if($debug == 1) echo 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']."<br>\n"; 
        
    } else { 
        if($debug == 1) echo 'Curl error: ' . curl_error($curl)."<br>\n";
        $error_flag = 1;
        $errors['curl_request'] .= curl_error($curl)."\n";
    } 
    
    return $response;
}

function getTransportAgreements($debug=0) {
    global $crg_api_key, $crg_sender_id, $crg_transport_url, $curl;

    curl_setopt($curl, CURLOPT_URL, $crg_transport_url);
    curl_setopt($curl, CURLOPT_POST, 0);
    
    $headers = array(
        "X-Cargonizer-Key:".$crg_api_key,
        "X-Cargonizer-Sender:".$crg_sender_id,
        "Content-type:application/xml",
        "Content-length:0",
    );
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    
    return runRequest($debug);
}

echo "getTransportAgreements, URL: $crg_transport_url<br>\n";

$response = getTransportAgreements(1);

echo "<pre>".print_r($response,1)."</pre>";

?> 
