<?php
/**
 * Novalnet payment plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 * that is bundled with this package in the file freeware_license_agreement.txt
 *
 * DISCLAIMER
 *
 * If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
 *
 * @category Novalnet
 * @package NovalPayment
 * @copyright Copyright (c) Novalnet
 * @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz GNU General Public License
 */

namespace Shopware\CustomModels\Transaction;

use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;

/**
* @ORM\Entity
* @ORM\Table(name="s_novalnet_transaction_detail")
*/
class Transaction extends ModelEntity
{
    /**
      * Primary Key - autoincrement value
      *
      * @var integer $id
      *
      * @ORM\Column(name="id", type="integer", nullable=false)
      * @ORM\Id
      * @ORM\GeneratedValue(strategy="IDENTITY")
      */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="tid", type="bigint", length=20, nullable=false)
     */
    private $tid;

    /**
     * @var string
     *
     * @ORM\Column(name="payment_type", type="string", length=50,nullable=false)
     *
     */
    private $paymentType;

    /**
     * @var integer
     *
     * @ORM\Column(name="amount", type="integer",nullable=false)
     *
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="currency", type="string",nullable=false)
     *
     */
    private $currency;

    /**
     * @var integer
     *
     * @ORM\Column(name="paid_amount", type="integer",nullable=false)
     *
     */
    private $paidAmount;

    /**
     * @var integer
     *
     * @ORM\Column(name="refunded_amount", type="integer", nullable=true, length=8)
     *
     */
    private $refundedAmount;

    /**
     * @var string
     *
     * @ORM\Column(name="gateway_status", type="string", nullable=true, length=100)
     *
     */
    private $gatewayStatus;

    /**
     * @var string $orderNo
     *
     * @ORM\Column(name="order_no", type="string", length=30)
     *
     */
    private $orderNo;

    /**
     * @var integer
     *
     * @ORM\Column(name="customer_id", type="integer", nullable=true)
     *
     */
    private $customerId;

    /**
     * @var string
     *
     * @ORM\Column(name="configuration_details", type="text", nullable=true)
     *
     */
    private $configurationDetails;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

   /**
    * @param int $id
    */
    public function setId($id)
    {
        $this->id = $id;
    }

   /**
    * @return int
    */
    public function getTid()
    {
        return $this->tid;
    }

   /**
    * @param int $tid
    */
    public function setTid($tid)
    {
        $this->tid = $tid;
    }

   /**
    * @return string
    */
    public function getPaymentType()
    {
        return $this->paymentType;
    }

   /**
    * @param string $paymentType
    */
    public function setPaymentType($paymentType)
    {
        $this->paymentType = $paymentType;
    }

   /**
    * @return int
    */
    public function getAmount()
    {
        return $this->amount;
    }

   /**
    * @param int $amount
    */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
    * @return string
    */
    public function getCurrency()
    {
        return $this->currency;
    }

   /**
    * @param string $currency
    */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

   /**
    * @return int
    */
    public function getPaidAmount()
    {
        return $this->paidAmount;
    }

   /**
    * @param int $paidAmount
    */
    public function setPaidAmount($paidAmount)
    {
        $this->paidAmount = $paidAmount;
    }

   /**
    * @return int
    */
    public function getRefundedAmount()
    {
        return $this->refundedAmount;
    }

   /**
    * @param int $refundedAmount
    */
    public function setRefundedAmount($refundedAmount)
    {
        $this->refundedAmount = $refundedAmount;
    }

   /**
    * @return string
    */
    public function getGatewayStatus()
    {
        return $this->gatewayStatus;
    }

   /**
    * @param string $gatewayStatus
    */
    public function setGatewayStatus($gatewayStatus)
    {
        $this->gatewayStatus = $gatewayStatus;
    }

   /**
    * @return string
    */
    public function getOrderNo()
    {
        return $this->orderNo;
    }

   /**
    * @param string $orderNo
    */
    public function setOrderNo($orderNo)
    {
        $this->orderNo = $orderNo;
    }

   /**
    * @return int
    */
    public function getCustomerId()
    {
        return $this->customerId;
    }

   /**
    * @param int $customerId
    */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
    }

   /**
    * @return string
    */
    public function getConfigurationDetails()
    {
        return $this->configurationDetails;
    }

   /**
    * @param string $configurationDetails
    */
    public function setConfigurationDetails($configurationDetails)
    {
        $this->configurationDetails = $configurationDetails;
    }
}
