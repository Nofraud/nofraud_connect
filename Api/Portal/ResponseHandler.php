<?php
 
namespace NoFraud\Connect\Api\Portal;
 
use NoFraud\Connect\Logger\Logger;

class ResponseHandler
{
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
     * Build NF Comment
     * @param $resultMap
     * @param null $commentType
     * @return false|string|void
     */
    public function buildComment($resultMap, $commentType = null)
    {
        if (isset($resultMap['http']['client']['error'])) {
            return "Failed to connect with the NoFraud service due to an error.";
        }

        if (isset($resultMap['http']['response']['body'])) {
            $responseBody = $resultMap['http']['response']['body'];
            if (isset($responseBody['Errors'])) {
                return $this->commentFromNoFraudErrors($responseBody['Errors']);
            } elseif ($commentType == 'cancel') {
                return $this->commentFromNoFraudCancellation($responseBody);
            } else {
                return $this->commentFromNoFraudDecision($responseBody);
            }
        }

        if (isset($resultMap['http']['response']['code'])) {
            return $this->commentFromResponseCode($resultMap['http']['response']['code']);
        }
    }

    /**
     * Add comment From NoFraud Cancellation
     * @param $responseBody
     * @return false|string
     */
    protected function commentFromNoFraudCancellation($responseBody)
    {
        if ($responseBody['code'] == 403) {
            return false;
        }
        return 'NoFraud Transaction Cancel: ' . $responseBody['message'];
    }

    /**
     * Add comment from NoFraud decision
     * @param $responseBody
     * @return string
     */
    protected function commentFromNoFraudDecision($responseBody): string
    {
        $id = $responseBody['id'];
        $decision = $responseBody['decision'];

        $comment = "NoFraud decision: " . strtoupper($decision) .
            ' ( ' . $this->noFraudLink($id, 'view report') . ' )';
        
        if ($decision == "review") {
            $comment .= "<br>This transaction is being looked into on your behalf.";
        }

        return $comment;
    }

    /**
     * Add comment from NoFraud errors
     * @param $errors
     * @return string
     */
    protected function commentFromNoFraudErrors($errors): string
    {
        $err = count($errors) > 1 ? 'errors' : 'error' ;

        $comment = "NoFraud was unable to assess this transaction due to the following {$err}:";
        foreach ($errors as $error) {
            $comment .= "<br>\"{$error}\"" ;
        }

        return $comment;
    }

    /**
     * Build NF portal link
     * @param $transactionId
     * @param $linkText
     * @return string
     */
    protected function noFraudLink($transactionId, $linkText): string
    {
        return "<a target=\"_blank\" href=\"https://portal.nofraud.com/transaction/{$transactionId}\">{$linkText}</a>" ;
    }

    /**
     * @param $responseCode
     * @return string
     */
    protected function commentFromResponseCode($responseCode): string
    {
        switch ($responseCode) {
            case 403:
                $comment = "Failed to authenticate with NoFraud. Please ensure that you have correctly " .
                    "entered your API Token under 'Stores > Configuration > NoFraud > Connect'.";
                break;

            default:
                $comment = "We're sorry. It appears the NoFraud service was unavailable at the time" .
                    " of this transaction.";
                break;
        }

        return $comment;
    }
}
