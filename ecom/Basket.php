<?php
/**
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @date 23.01.14
 */

namespace opus\ecom;

use opus\ecom\basket\Item;
use opus\ecom\basket\StorageInterface;
use opus\ecom\models\OrderableInterface;
use opus\ecom\models\PurchasableInterface;
use yii\base\InvalidParamException;
use yii\base\Object;
use yii\web\Session;

/**
 * Provides basic basket functionality (adding, removing, clearing, listing items). You can extend this class and
 * override it in the application configuration to extend/customize the functionality
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package opus\ecom
 *
 * @property int $count
 */
class Basket extends Object
{
    use SubComponentTrait;
    /**
     * @var Session
     */
    private $_session;
    /**
     * Override this to provide custom (e.g. database) storage for basket data
     *
     * @var string|\opus\ecom\basket\StorageInterface
     */
    private $_storage = 'opus\ecom\basket\storage\Session';

    /**
     * @var string Internal class name for holding basket elements
     */
    public $itemClass = 'opus\ecom\basket\Item';
    /**
     * @var array
     */
    protected $items = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->storage = \Yii::createObject($this->storage);
        $this->setItems($this->storage->load($this));
    }

    /**
     * @param OrderableInterface $order
     * @param bool $clear
     * @throws \Exception
     */
    public function createOrder(OrderableInterface $order, $clear = true)
    {
        try
        {
            $order->saveFromBasket($this);
            $clear && $this->clear();
        }
        catch (\Exception $exception)
        {
            throw $exception;
        }
    }

    /**
     * @param PurchasableInterface $element
     * @param array $options
     * @param bool $save
     */
    public function add(PurchasableInterface $element, array $options = [], $save = true)
    {
        $className = $this->itemClass;

        /** @var $className Item */
        $item = new $className($element, $options + ['basket' => $this]);
        $this->addItem($item);

        $save && $this->storage->save($this);
    }

    /**
     * @param bool $save
     */
    public function clear($save = true)
    {
        $this->items = [];
        $save && $this->storage->save($this);
    }

    /**
     * @param string $uniqueId
     * @param bool $save
     * @throws \yii\base\InvalidParamException
     * @return $this
     */
    public function remove($uniqueId, $save = true)
    {
        if (!isset($this->items[$uniqueId])) {
            throw new InvalidParamException('Item not found');
        }
        unset($this->items[$uniqueId]);
        $save && $this->storage->save($this);
        return $this;
    }

    /**
     * @param Item[] $items
     */
    protected function setItems(array $items)
    {
        $this->clear(false);
        foreach ($items as $item) {
            $item->basket = $this;
            $this->addItem($item);
        }
    }

    /**
     * @return Item[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param Item $item
     */
    protected function addItem(Item $item)
    {
        $this->items[$item->uniqueId] = $item;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return count($this->items);
    }

    /**
     * @param bool $format
     * @return float|int|string
     */
    public function getTotalDue($format = true)
    {
        $sum = 0;
        foreach ($this->getItems() as $item)
        {
            $sum += $item->getTotalPrice();
        }
        return $format ? $this->component->formatter->asPrice($sum) : $sum;
    }

    /**
     * @param \yii\web\Session $session
     * @return Basket
     */
    public function setSession(Session $session)
    {
        $this->_session = $session;
        return $this;
    }

    /**
     * @return \yii\web\Session
     */
    public function getSession()
    {
        return $this->_session;
    }

    /**
     * @param \opus\ecom\basket\StorageInterface|string $storage
     * @return Basket
     */
    public function setStorage(StorageInterface $storage)
    {
        $this->_storage = $storage;
        return $this;
    }

    /**
     * @return \opus\ecom\basket\StorageInterface|string
     */
    protected function getStorage()
    {
        return $this->_storage;
    }
} 