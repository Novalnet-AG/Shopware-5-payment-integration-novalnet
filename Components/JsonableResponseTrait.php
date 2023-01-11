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

namespace Shopware\Plugins\NovalPayment\Components;

use Exception;
use InvalidArgumentException;

/**
 * Helper to return json responses from controllers.
 */
trait JsonableResponseTrait
{
    /**
     * @param array $data
     *
     * @throws InvalidArgumentException
     */
    public function jsonResponse($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('The given input can not be JSON serialized.');
        }

        $this->Response()->setHeader('Content-Type', 'application/json', true);
        $this->Response()->setBody(json_encode($data));
    }

    public function jsonException(Exception $e)
    {
        $this->jsonResponse([
            'success' => false,
            'error'   => $e->getMessage(),
        ]);
    }
}
