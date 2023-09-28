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

namespace Shopware\Plugins\NovalPayment\Components\Classes;

use Shopware\CustomModels\Api\Api;
use Shopware\CustomModels\Transaction\Transaction;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Order\Order;
use PDO;

class DataHandler
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em         = $em;
        $this->connection = $em->getConnection();
        $this->queryBuilder = $this->connection->createQueryBuilder();
    }
    
    /**
     * check novalnet transaction already is exists.
     *
     * @param array $condition
     *
     * @return null|Transaction
     */
    public function checkTransactionExists(array $condition)
    {
        /** @var Transaction $transactionRepository */
        $transactionRepository = $this->em->getRepository('Shopware\CustomModels\Transaction\Transaction');

        // Find the API
        return $transactionRepository->findOneBy($condition);
    }
    
    /**
     * Insert the API credentials into the database.
     *
     * @param array $data
     *
     * @return void
     */
    public function insertApiData(array $data)
    {
        $existingApi = $this->checkExistingApi($data['shop_id']);

        if (!empty($existingApi)) {
            $this->connection->update('s_novalnet_api', $data, ['shop_id' => $data['shop_id']]);
        } else {
            $this->connection->insert('s_novalnet_api', $data);
        }
    }
    
     /**
     * check API credentials is exists.
     *
     * @param int $shopId
     *
     * @return null|Api
     */
    public function checkExistingApi(int $shopId)
    {
        /** @var Api $apiRepository */
        $apiRepository = $this->em->getRepository('Shopware\CustomModels\Api\Api');

        // Find the API
        return $apiRepository->findOneBy(['shopId' => $shopId]);
    }
    
    /**
     * get payment method object.
     *
     * @param array $data
     *
     * @return null|Payment
     */
    public function getPaymentMethod(array $data)
    {
        /** @var Payment $paymentRepository */
        $paymentRepository = $this->em->getRepository('Shopware\Models\Payment\Payment');

        // Find the active payment methods
        return $paymentRepository->findOneBy($data);
    }
    
    /**
     * Update the s_order core table
     *
     * @param array $data
     */
    public function updateOrdertable(array $data)
    {
        $condition = ['ordernumber' => $data['ordernumber']];
        $this->connection->update('s_order', $data, $condition);
    }
    
    /**
     * Insert the Novalnet transaction data into the database.
     *
     * @param array $data
     *
     * @return void
     */
    public function insertNovalnetTransaction(array $data)
    {
        $condition = ['tid' => $data['tid']];
        $existingTransaction = $this->checkTransactionExists($condition);
        
        if (!empty($existingTransaction)) {
            $this->connection->update('s_novalnet_transaction_detail', $data, $condition);
        } else {
            $this->connection->insert('s_novalnet_transaction_detail', $data);
        }
    }
    
    /**
     * Insert the Novalnet subscription payment data into the database.
     *
     * @param array $data
     *
     * @return void
     */
    public function insertNNSubscriptionPaymentData(array $data)
    {
        if ($data) {
            $this->connection->insert('s_novalnet_change_subscription_payment', $data);
        }
    }
    
    /**
     * Get Order Reference.
     *
     * @param string $reference
     *
     * @return null|array
     */
    public function getOrder($reference)
    {
        $orderRespository = $this->em->getRepository('Shopware\Models\Order\Order');
        $query = $orderRespository->getOrdersQuery([['property' => 'orders.number', 'value' => $reference]], null, 0, 1);
        $queryResult = $query->getArrayResult();
        if (empty($queryResult)) {
            $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE transactionID = ?', array($reference));
            if (empty($id)) {
                return null;
            }
            $query = $orderRespository->getOrdersQuery([['property' => 'orders.id', 'value' => $id]], null, 0, 1);
        }
                
        return $queryResult[0];
    }
    
    /**
     * Update the Novalnet transaction data into the database.
     *
     * @param string $comment
     * @param array $data
     * @param bool $append
     * @param string $zeroAmountTid
     *
     * @return void
     */
    public function postProcess($comment, $data = [], $append = true, $zeroAmountTid = null)
    {
        if (!empty($data) && isset($data['id'])) {
            $this->connection->update('s_novalnet_transaction_detail', $data, ['id' => $data['id']]);
        }
        
        $comment = str_replace('<br />', PHP_EOL, $comment);

        if (!empty($append)) {
            Shopware()->Db()->query('update s_order set customercomment = CONCAT(customercomment,?) where transactionID = ?', [$comment, $data['tid']]);
        } elseif ($zeroAmountTid) {
            Shopware()->Db()->query('update s_order set customercomment = ?, transactionID = ? ,temporaryID = ? where transactionID = ?', [$comment,$data['tid'],$data['tid'],$zeroAmountTid]);
        } else {
            Shopware()->Db()->query('update s_order set customercomment = ? where transactionID = ?', [$comment, $data['tid']]);
        }
    }
}
