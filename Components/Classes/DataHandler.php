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
     * Update the Novalnet transaction data into the database.
     *
     * @param string $comment
     * @param array $data
     * @param bool $append
     *
     * @return void
     */
    public function postProcess($comment, $data = null, $append = true)
    {
        if (!empty($data) && isset($data['id'])) {
            $this->connection->update('s_novalnet_transaction_detail', $data, ['id' => $data['id']]);
        }

        $comment = '<br />' . $comment;
        $comment = str_replace('<br />', PHP_EOL, $comment);


        if (!empty($append)) {
            Shopware()->Db()->query('update s_order set customercomment = CONCAT(customercomment,?) where transactionID = ?', [$comment, $data['tid']]);
        } else {
            Shopware()->Db()->query('update s_order set customercomment = ? where transactionID = ?', [$comment, $data['tid']]);
        }
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
        $transactionRepository = $this->em->getRepository(Transaction::class);

        // Find the API
        return $transactionRepository->findOneBy($condition);
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
        $apiRepository = $this->em->getRepository(Api::class);

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
        $paymentRepository = $this->em->getRepository(Payment::class);

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
     * Generate Check Sum Token
     *
     * @param string $payment
     * @param array $userInfo
     *
     * @return null|array
     */
    public function getOneClickDetails($payment, $userInfo)
    {
        if (in_array($payment, ['novalnetsepa','novalnetsepaGuarantee','novalnetsepainstalment'])) {
            $payment = 'novalnetsepa';
        }

        $this->queryBuilder->select('configuration_details')
                    ->from('s_novalnet_transaction_detail')
                    ->where("payment_type LIKE '%$payment%'")
                    ->Andwhere('customer_id  = :customer_id')
                    ->Andwhere("configuration_details LIKE '%token%'")
                    ->setParameter('customer_id', $userInfo['additional']['user']['customernumber'])
                    ->orderBy('id', 'DESC')
                    ->setMaxResults(3);
        $result = $this->queryBuilder->execute()->fetchAll(PDO::FETCH_COLUMN);
        return $result;
    }

    /**
     * Delete Payment token from the table
     *
     * @param array $data
     *
     * @return void
     */
    public function deleteCardToken($data)
    {
        if (!empty($data['token']) && !empty($data['customer_no'])) {
            $this->queryBuilder->select('configuration_details, tid')
                        ->from('s_novalnet_transaction_detail')
                        ->where('customer_id  = :customer_id')
                        ->Andwhere("configuration_details LIKE '%".$data['token']."%'")
                        ->setParameter('customer_id', $data['customer_no'])
                        ->orderBy('id', 'DESC')
                        ->setMaxResults(1);
            $result = $this->queryBuilder->execute()->fetchAll();
            if (empty($result)) {
                return;
            }
            $unserializedData = json_decode($result[0]['configuration_details'], true, 512, JSON_BIGINT_AS_STRING);
            unset($unserializedData['token']);

            $updateData ['configuration_details'] = json_encode($unserializedData, JSON_UNESCAPED_SLASHES);

            $this->connection->update('s_novalnet_transaction_detail', $updateData, ['tid' => $result[0]['tid']]);
        }
        return;
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
        $orderRespository = $this->em->getRepository(Order::class);
        $query = $orderRespository->getOrdersQuery([['property' => 'orders.number', 'value' => $reference]], null, 0, 1);
        if (empty($query->getArrayResult())) {
            $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE transactionID = ?', array($reference));
            if (!empty($id)) {
                return null;
            }
            $query = $orderRespository->getOrdersQuery([['property' => 'orders.id', 'value' => $id]], null, 0, 1);
        }
        $data = $query->getArrayResult();
        return $data[0];
    }
}
