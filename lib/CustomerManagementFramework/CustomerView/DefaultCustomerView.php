<?php

namespace CustomerManagementFramework\CustomerView;

use CustomerManagementFramework\Model\CustomerInterface;
use CustomerManagementFramework\View\Formatter\ObjectWrapper;
use CustomerManagementFramework\View\Formatter\ViewFormatterInterface;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Object\Concrete;
use Pimcore\Model\Object\Fieldcollection;

class DefaultCustomerView implements CustomerViewInterface
{
    /**
     * @var ViewFormatterInterface
     */
    protected $viewFormatter;

    /**
     * @param ViewFormatterInterface $viewFormatter
     */
    public function __construct(ViewFormatterInterface $viewFormatter)
    {
        $this->viewFormatter = $viewFormatter;
    }

    /**
     * @return ViewFormatterInterface
     */
    public function getViewFormatter()
    {
        return $this->viewFormatter;
    }

    /**
     * @param CustomerInterface $customer
     * @return string|null
     */
    public function getOverviewTemplate(CustomerInterface $customer)
    {
        return 'customers/partials/list-row.php';
    }

    /**
     * Determines if customer has a detail view or if pimcore object should be openend directly
     *
     * @param CustomerInterface $customer
     * @return bool
     */
    public function hasDetailView(CustomerInterface $customer)
    {
        return true;
    }

    /**
     * @param CustomerInterface $customer
     * @return string|null
     */
    public function getDetailviewTemplate(CustomerInterface $customer)
    {
        return 'customers/partials/detail.php';
    }

    /**
     * @param CustomerInterface|ElementInterface|Concrete $customer
     * @return array
     */
    public function getDetailviewData(CustomerInterface $customer)
    {
        $definition = $customer->getClass();

        $result = [];
        $vf     = $this->viewFormatter;

        foreach ($definition->getFieldDefinitions() as $fd) {
            if ($fd->getInvisible()) {
                continue;
            }

            $getter = 'get' . ucfirst($fd->getName());
            $value  = $vf->formatValueByFieldDefinition($fd, $customer->$getter());

            if (is_object($value)) {
                $value = $this->wrapObject($value);
            }

            $result[$vf->getLabelByFieldDefinition($fd)] = $vf->formatValueByFieldDefinition($fd, $value);
        }

        return $result;
    }

    /**
     * Wrap object in a object implementing a __toString method
     *
     * @param $object
     * @return ObjectWrapper
     */
    protected function wrapObject($object)
    {
        return new ObjectWrapper($object);
    }

    /**
     * @param string $value
     * @return string
     */
    public function translate($value)
    {
        return $this->viewFormatter->translate($value);
    }
}