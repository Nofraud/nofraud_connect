<?php
 
namespace NoFraud\Connect\Api\Portal;
 
class ResponseHandler
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param \Magento\Framework\Escaper $escaper
     */
    public function __construct(
        \NoFraud\Connect\Logger\Logger $logger,
        \Magento\Framework\Escaper $escaper
    ) {
        $this->logger = $logger;
        $this->escaper = $escaper;
    }

    /**
     * Build Comment
     *
     * @param mixed $resultMap
     * @param mixed $commentType
     * @return void
     */
    public function buildComment($resultMap, $commentType = null)
    {
        if (isset($resultMap['http']['client']['error'])) {
            $comment = "Failed to connect with the NoFraud service due to an error.";
            return $comment;
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
     * Comment From NoFraud Cancellation
     *
     * @param mixed $responseBody
     * @return void
     */
    protected function commentFromNoFraudCancellation($responseBody)
    {
        if ($responseBody['code'] == 403) {
            return false;
        }

        return 'NoFraud Transaction Cancel: ' . $this->escaper->escapeHtml($responseBody['message'] ?? '');
    }

    /**
     * Comment From NoFraud Decision
     *
     * @param mixed $responseBody
     * @return void
     */
    protected function commentFromNoFraudDecision($responseBody)
    {
        $id       = $responseBody['id'];
        $decision = $responseBody['decision'];

        $comment  = "NoFraud decision: " . $this->escaper->escapeHtml(strtoupper((string)$decision));
        $comment .= ' ( ' . $this->noFraudLink($id, 'view report') . ' )' ;
        
        if ($decision == "review") {
            $comment .= "<br>This transaction is being looked into on your behalf.";
        }

        return $comment;
    }

    /**
     * Comment From NoFraud Errors
     *
     * @param mixed $errors
     * @return void
     */
    protected function commentFromNoFraudErrors($errors)
    {
        $error_s = count($errors) > 1 ? 'errors' : 'error' ;

        $comment = "NoFraud was unable to assess this transaction due to the following {$error_s}:";
        foreach ($errors as $error) {
            $comment .= '<br>"' . $this->escaper->escapeHtml($error) . '"';
        }

        return $comment;
    }

    /**
     * No Fraud Link
     *
     * @param mixed $transactionId
     * @param mixed $linkText
     * @return void
     */
    protected function noFraudLink($transactionId, $linkText)
    {
        $url = 'https://portal.nofraud.com/transaction/' . rawurlencode((string)$transactionId);

        return '<a target="_blank" href="' . $this->escaper->escapeUrl($url) . '">'
            . $this->escaper->escapeHtml($linkText) . '</a>';
    }

    /**
     * Comment From Response Code
     *
     * @param mixed $responseCode
     * @return void
     */
    protected function commentFromResponseCode($responseCode)
    {
        switch ($responseCode) {
            case 403:
                $comment  = "Failed to authenticate with NoFraud.";
                $comment .= " Please ensure that you have correctly entered your API Token under";
                $comment .= " 'Stores > Configuration > NoFraud > Connect'.";
                break;

            default:
                $comment  = "We're sorry. It appears the NoFraud service was";
                $comment .= " unavailable at the time of this transaction.";
                break;
        }

        return $comment;
    }
}
