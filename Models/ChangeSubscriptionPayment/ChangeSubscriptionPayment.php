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

namespace Shopware\CustomModels\ChangeSubscriptionPayment;

use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;

/**
* @ORM\Entity
* @ORM\Table(name="s_novalnet_change_subscription_payment")
*/
class ChangeSubscriptionPayment extends ModelEntity
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
     * @var DateTimeInterface|null
     *
     * @ORM\Column(name="datum", type="datetime", nullable=true)
     */
    private $date;

    /**
     * @var integer $aboId
     *
     * @ORM\Column(name="abo_id", type="integer", nullable=true)
     *
     */
    private $aboId;

    /**
     * @var integer
     *
     * @ORM\Column(name="customer_id", type="integer", nullable=true)
     *
     */
    private $customerId;
          
     /**
     * @var string $orderNo
     *
     * @ORM\Column(name="order_no", type="string", length=30)
     *
     */
    private $orderNo;

    /**
     * @var string
     *
     * @ORM\Column(name="payment_data", type="text", nullable=true)
     *
     */
    private $payment_data;

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
     * @return DateTimeInterface|null
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param DateTimeInterface|null $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

   /**
    * @return string
    */
    public function getAboId()
    {
        return $this->aboId;
    }

   /**
    * @param string $aboId
    */
    public function setAboId($aboId)
    {
        $this->aboId = $aboId;
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
    * @return string
    */
    public function getPaymentData()
    {
        return $this->payment_data;
    }

   /**
    * @param string $payment_data
    */
    public function setPaymentData($payment_data)
    {
        $this->payment_data = $payment_data;
    }
}
