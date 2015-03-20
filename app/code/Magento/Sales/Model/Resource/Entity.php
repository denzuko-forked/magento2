<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Model\Resource;

use Magento\Framework\Model\Resource\Db\AbstractDb;
use Magento\Sales\Model\EntityInterface;
use Magento\SalesSequence\Model\Sequence\SequenceReader;

/**
 * Flat sales resource abstract
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class Entity extends AbstractDb
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'sales_order_resource';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'resource';

    /**
     * Use additional is object new check for this resource
     *
     * @var bool
     */
    protected $_useIsObjectNew = true;

    /**
     * @var \Magento\Eav\Model\Entity\TypeFactory
     */
    protected $_eavEntityTypeFactory;

    /**
     * @var \Magento\Sales\Model\Resource\Attribute
     */
    protected $attribute;

    /**
     * @var SequenceReader
     */
    protected $sequenceReader;

    /**
     * @var \Magento\Sales\Model\Resource\GridInterface
     */
    protected $gridAggregator;

    /**
     * @param \Magento\Framework\Model\Resource\Db\Context $context
     * @param Attribute $attribute
     * @param SequenceReader $sequenceReader
     * @param string|null $resourcePrefix
     * @param GridInterface|null $gridAggregator
     */
    public function __construct(
        \Magento\Framework\Model\Resource\Db\Context $context,
        \Magento\Sales\Model\Resource\Attribute $attribute,
        SequenceReader $sequenceReader,
        $resourcePrefix = null,
        \Magento\Sales\Model\Resource\GridInterface $gridAggregator = null
    ) {
        $this->attribute = $attribute;
        $this->sequenceReader = $sequenceReader;
        $this->gridAggregator = $gridAggregator;
        parent::__construct($context, $resourcePrefix);
    }

    /**
     * Perform actions after object save
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param string $attribute
     * @return $this
     * @throws \Exception
     */
    public function saveAttribute(\Magento\Framework\Model\AbstractModel $object, $attribute)
    {
        $this->attribute->saveAttribute($object, $attribute);
        return $this;
    }

    /**
     * Perform actions before object save
     *
     * @param \Magento\Framework\Model\AbstractModel|\Magento\Framework\Object $object
     * @return $this
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object instanceof EntityInterface && $object->getIncrementId() == null) {
            $object->setIncrementId($this->sequenceReader->getSequence($object)->getNextValue());
        }
        parent::_beforeSave($object);
        return $this;
    }

    /**
     * Save object data
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    public function save(\Magento\Framework\Model\AbstractModel $object)
    {
        if (!$object->getForceObjectSave()) {
            parent::save($object);
        }

        return $this;
    }

    /**
     * Perform actions after object save
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($this->gridAggregator) {
            $this->gridAggregator->refresh($object->getId());
        }

        $adapter = $this->_getReadAdapter();
        $columns = $adapter->describeTable($this->getMainTable());

        if (isset($columns['created_at'], $columns['updated_at'])) {
            $select = $adapter->select()
                ->from($this->getMainTable(), ['created_at', 'updated_at'])
                ->where($this->getIdFieldName() . ' = :entity_id');
            $row = $adapter->fetchRow($select, [':entity_id' => $object->getId()]);

            if (is_array($row) && isset($row['created_at'], $row['updated_at'])) {
                $object->setCreatedAt($row['created_at']);
                $object->setUpdatedAt($row['updated_at']);
            }
        }

        parent::_afterSave($object);
        return $this;
    }

    /**
     * Perform actions after object delete
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function _afterDelete(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($this->gridAggregator) {
            $this->gridAggregator->purge($object->getId());
        }
        parent::_afterDelete($object);
        return $this;
    }
}
