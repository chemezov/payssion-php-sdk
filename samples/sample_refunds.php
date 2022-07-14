<?php

$payssion = new PayssionClient('your api key', 'your secretkey');
//please uncomment the following if you use sandbox api_key
//$payssion = new PayssionClient('your api key', 'your secretkey', false);

$response = null;
try {
    $response = $payssion->refund([
        'transaction_id' => 'your transaction id', // your transaction id
        'amount' => 1,
        'currency' => 'USD',
    ]);
} catch (Exception $e) {
    //handle exception
    echo "Exception: " . $e->getMessage();
}

if ($payssion->isSuccess()) {
    //handle success
    $refund = $response['refund'];

    $transaction_id = $refund['transaction_id'];
    $original_transaction_id = $refund['original_transaction_id'];
    $state = $refund['state'];
    $amount = $refund['amount'];
    $currency = $refund['currency'];

} else {
    //handle failed
}
