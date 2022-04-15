<?php
 
namespace NoFraud\Connect\Api;
 
use NoFraud\Connect\Logger\Logger;

/**
 * Process NF response
 */
class ResponseHandler
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Will return transaction info
     * @param $resultMap
     * @return array|void
     */
    public function getTransactionData($resultMap)
    {
        if (isset($resultMap['http']['client']['error'])) {
            return $this->prepareErrorData("Failed to connect with the NoFraud service due to an error.");
        }

        if (isset($resultMap['http']['response']['body'])) {
            $responseBody = $resultMap['http']['response']['body'];
            if (isset($responseBody['Errors'])) {
                return $this->commentFromNoFraudErrors($responseBody['Errors']);
            } else {
                return $this->commentFromNoFraudDecision($responseBody);
            }
        }

        if (isset($resultMap['http']['response']['code'])) {
            return $this->commentFromResponseCode($resultMap['http']['response']['code']);
        }
    }

    /**
     * Add comment from no fraud decision
     * @param array $responseBody
     * @return array
     */
    protected function commentFromNoFraudDecision(array $responseBody): array
    {
        $id       = $responseBody['id'];
        $decision = $responseBody['decision'];

        $comment = "NoFraud decision: " . strtoupper($decision) .
            ' ( ' . $this->noFraudLink($id, 'view report') . ' )';
        
        if ($decision == "review") {
            $comment .= "<br>This transaction is being looked into on your behalf.";
        }

        return $this->prepareData($comment, $decision, $id);
    }

    /**
     * Add comment From NF Errors
     * @param $errors
     * @return array
     */
    protected function commentFromNoFraudErrors($errors): array
    {
        $allErrors = count($errors) > 1 ? 'errors' : 'error' ;

        $comment = "NoFraud was unable to assess this transaction due to the following {$allErrors}:";
        foreach ($errors as $error) {
            $comment .= "<br>\"{$error}\"" ;
        }

        return $this->prepareErrorData($comment);
    }

    /**
     * Will return NF link
     * @param $transactionId
     * @param $linkText
     * @return string
     */
    protected function noFraudLink($transactionId, $linkText): string
    {
        return "<a target=\"_blank\" href=\"https://portal.nofraud.com/transaction/{$transactionId}\">{$linkText}</a>" ;
    }

    /**
     * Add comment from response code
     * @param $responseCode
     * @return array
     */
    protected function commentFromResponseCode($responseCode): array
    {
        switch ($responseCode) {
            case 403:
                $comment = "Failed to authenticate with NoFraud. Please ensure that you have correctly entered your" .
                    " API Token under 'Stores > Configuration > NoFraud > Connect'.";
                break;

            default:
                $comment = "We're sorry. It appears the NoFraud service was unavailable at the time of this " .
                    "transaction.";
                break;
        }

        return $this->prepareErrorData($comment);
    }

    /**
     * Prepare error data
     * @param $comment
     * @return array
     */
    private function prepareErrorData($comment)
    {
        return $this->prepareData($comment, 'Error', null);
    }

    /**
     * Prepare data array
     * @param $comment
     * @param $status
     * @param $id
     * @return array
     */
    private function prepareData($comment, $status, $id): array
    {
        return array(
            'comment' => $comment,
            'status' => $status,
            'id' => $id,
        );
    }
}
