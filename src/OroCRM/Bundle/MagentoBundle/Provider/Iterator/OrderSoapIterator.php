<?php

namespace OroCRM\Bundle\MagentoBundle\Provider\Iterator;

use Oro\Bundle\IntegrationBundle\Utils\ConverterUtils;

use OroCRM\Bundle\MagentoBundle\Provider\Transport\SoapTransport;

class OrderSoapIterator extends AbstractPageableSoapIterator
{
    /**
     * {@inheritdoc}
     */
    public function getEntityIds()
    {
        $filters = $this->getBatchFilter($this->lastSyncDate, [], $this->getStoresByWebsiteId($this->websiteId));

        $result = $this->transport->call(SoapTransport::ACTION_ORDER_LIST, [$filters]);
        $result = is_array($result) ? $result : [];

        $idFieldName = $this->getIdFieldName();
        $result      = array_map(
            function ($item) use ($idFieldName) {
                $inc = is_object($item) ? $item->increment_id : $item['increment_id'];
                $id  = is_object($item) ? $item->order_id : $item['order_id'];

                return (object)['increment_id' => $inc, $idFieldName => $id];
            },
            $result
        );

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEntity($id)
    {
        $result = $this->transport->call(SoapTransport::ACTION_ORDER_INFO, [$id->increment_id]);

        $this->addDependencyData($result);

        return ConverterUtils::objectToArray($result);

    }

    /**
     * {@inheritdoc}
     */
    protected function addDependencyData($result)
    {
        parent::addDependencyData($result);
        $result->payment_method = isset($result->payment, $result->payment->method) ? $result->payment->method : null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDependencies()
    {
        return [
            self::ALIAS_STORES   => iterator_to_array($this->transport->getStores()),
            self::ALIAS_WEBSITES => iterator_to_array($this->transport->getWebsites())
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdFieldName()
    {
        return 'order_id';
    }
}
